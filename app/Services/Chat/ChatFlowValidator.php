<?php

namespace App\Services\Chat;

/**
 * Validates a conversation graph before it can be published.
 *
 * The builder must not let a tenant strand a visitor, so this catches the ways a
 * graph goes wrong: a missing start, a pointer to a node that no longer exists,
 * a question with no options, a branch that dead-ends, and nodes nothing can
 * reach. Errors block publishing; warnings are advisory.
 */
class ChatFlowValidator
{
    /** Node types the engine can execute. */
    public const TYPES = ['message', 'choice', 'input', 'condition', 'score', 'tag', 'assign', 'handoff', 'end', 'booking', 'ai'];

    /** Types that terminate a path rather than pointing onward. */
    private const TERMINAL = ['end'];

    /**
     * @param  array<string, mixed>  $flow
     * @return array{valid: bool, errors: list<array{node: ?string, message: string}>, warnings: list<array{node: ?string, message: string}>}
     */
    public function validate(array $flow): array
    {
        $errors = [];
        $warnings = [];

        $nodes = is_array($flow['nodes'] ?? null) ? $flow['nodes'] : [];
        $start = is_string($flow['start'] ?? null) ? $flow['start'] : null;

        if ($nodes === []) {
            return [
                'valid' => false,
                'errors' => [['node' => null, 'message' => 'The conversation has no steps yet. Add a first step to get started.']],
                'warnings' => [],
            ];
        }

        if ($start === null || ! isset($nodes[$start])) {
            $errors[] = ['node' => null, 'message' => 'No starting step is set, so the conversation cannot begin.'];
        }

        foreach ($nodes as $id => $node) {
            if (! is_array($node)) {
                $errors[] = ['node' => (string) $id, 'message' => 'This step is malformed.'];

                continue;
            }

            $type = $node['type'] ?? '';
            if (! in_array($type, self::TYPES, true)) {
                $errors[] = ['node' => (string) $id, 'message' => sprintf('Unknown step type "%s".', (string) $type)];

                continue;
            }

            // Every non-terminal step needs somewhere to go.
            if (in_array($type, ['message', 'input', 'score', 'tag', 'assign', 'handoff', 'booking', 'ai'], true)) {
                $errors = array_merge($errors, $this->checkTarget($nodes, (string) $id, $node['next'] ?? null, 'next step'));
            }

            // Booking and AI steps may name a fallback for when they cannot run
            // (nothing free, AI unavailable). Optional, but if named it must exist.
            if (in_array($type, ['booking', 'ai'], true) && ($node['fallback'] ?? null) !== null) {
                $errors = array_merge($errors, $this->checkTarget($nodes, (string) $id, $node['fallback'], 'fallback path'));
            }

            if ($type === 'choice') {
                $options = is_array($node['options'] ?? null) ? $node['options'] : [];
                if ($options === []) {
                    $errors[] = ['node' => (string) $id, 'message' => 'This question has no answers for the visitor to choose from.'];
                }
                // A question with nowhere to store its answer still routes the
                // visitor, but the answer is lost: it never reaches the CRM, the
                // inbox or a later condition. Worth flagging, not blocking.
                if (trim((string) ($node['field'] ?? '')) === '') {
                    $warnings[] = [
                        'node' => (string) $id,
                        'message' => 'This question does not save the answer anywhere, so it will not show on the lead.',
                    ];
                }
                $seen = [];
                foreach ($options as $option) {
                    if (! is_array($option) || ! isset($option['id'], $option['label']) || trim((string) $option['label']) === '') {
                        $errors[] = ['node' => (string) $id, 'message' => 'Every answer needs a label.'];

                        continue;
                    }
                    if (in_array($option['id'], $seen, true)) {
                        $errors[] = ['node' => (string) $id, 'message' => sprintf('Two answers share the id "%s".', (string) $option['id'])];
                    }
                    $seen[] = $option['id'];
                    $errors = array_merge(
                        $errors,
                        $this->checkTarget($nodes, (string) $id, $option['next'] ?? null, sprintf('answer "%s"', (string) $option['label'])),
                    );
                }
            }

            if ($type === 'condition') {
                if (trim((string) ($node['field'] ?? '')) === '') {
                    $errors[] = ['node' => (string) $id, 'message' => 'This condition does not say which answer to check.'];
                }
                $errors = array_merge($errors, $this->checkTarget($nodes, (string) $id, $node['next'] ?? null, '"if true" path'));
                $errors = array_merge($errors, $this->checkTarget($nodes, (string) $id, $node['otherwise'] ?? null, '"otherwise" path'));
            }

            if ($type === 'input' && trim((string) ($node['field'] ?? '')) === '') {
                $errors[] = ['node' => (string) $id, 'message' => 'This question does not say where to store the answer.'];
            }

            if (in_array($type, ['message', 'choice', 'input', 'ai'], true) && trim((string) ($node['text'] ?? '')) === '') {
                $errors[] = ['node' => (string) $id, 'message' => 'This step has no message text.'];
            }
        }

        // Anything the visitor can never arrive at is dead weight in the flow.
        if ($start !== null && isset($nodes[$start])) {
            $reachable = $this->reachableFrom($nodes, $start);
            foreach (array_keys($nodes) as $id) {
                if (! in_array((string) $id, $reachable, true)) {
                    $warnings[] = ['node' => (string) $id, 'message' => 'Nothing leads to this step, so a visitor will never see it.'];
                }
            }

            $hasEnd = false;
            foreach ($reachable as $id) {
                if (in_array($nodes[$id]['type'] ?? '', self::TERMINAL, true)) {
                    $hasEnd = true;
                    break;
                }
            }
            if (! $hasEnd) {
                $warnings[] = ['node' => null, 'message' => 'No ending step can be reached, so conversations will not be completed.'];
            }
        }

        return ['valid' => $errors === [], 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * @param  array<string, mixed>  $nodes
     * @return list<array{node: ?string, message: string}>
     */
    private function checkTarget(array $nodes, string $id, mixed $target, string $label): array
    {
        if ($target === null || $target === '') {
            return [['node' => $id, 'message' => sprintf('The %s is not connected to anything.', $label)]];
        }

        if (! isset($nodes[(string) $target])) {
            return [['node' => $id, 'message' => sprintf('The %s points at a step that no longer exists.', $label)]];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $nodes
     * @return list<string>
     */
    private function reachableFrom(array $nodes, string $start): array
    {
        $seen = [];
        $queue = [$start];

        while ($queue !== []) {
            $id = array_shift($queue);
            if (in_array($id, $seen, true) || ! isset($nodes[$id])) {
                continue;
            }
            $seen[] = $id;

            $node = $nodes[$id];
            if (! is_array($node)) {
                continue;
            }

            foreach (['next', 'otherwise', 'fallback'] as $key) {
                if (! empty($node[$key])) {
                    $queue[] = (string) $node[$key];
                }
            }

            foreach ((array) ($node['options'] ?? []) as $option) {
                if (is_array($option) && ! empty($option['next'])) {
                    $queue[] = (string) $option['next'];
                }
            }
        }

        return $seen;
    }
}
