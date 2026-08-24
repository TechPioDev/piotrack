<?php

namespace App\Console\Commands;

use App\Models\ChatWidget;
use App\Services\Chat\ChatFlowValidator;
use App\Services\Chat\DefaultChatFlow;
use Illuminate\Console\Command;

/**
 * Replace a widget's conversation with the current default flow.
 *
 * A widget stores its own copy of the flow, so improvements to the default reach
 * new widgets only. This is how an existing one catches up — and it is a
 * replacement, not a merge: anything edited in the builder is lost, which is why
 * it names what it is about to overwrite and asks first.
 */
class RefreshChatFlow extends Command
{
    protected $signature = 'chat:flow-refresh
                            {--widget= : Widget id; omit to list what would change}
                            {--force : Skip the confirmation}';

    protected $description = "Replace a chat widget's conversation with the current default flow";

    public function handle(ChatFlowValidator $validator): int
    {
        $flow = DefaultChatFlow::definition();

        $result = $validator->validate($flow);
        if (! $result['valid']) {
            $this->error('The default flow is not valid, so nothing was changed:');
            foreach ($result['errors'] as $error) {
                $this->line('  '.$error['message']);
            }

            return self::FAILURE;
        }

        $widgets = ChatWidget::withoutGlobalScope('tenant')
            ->when($this->option('widget'), fn ($q) => $q->whereKey($this->option('widget')))
            ->orderBy('id')
            ->get();

        if ($widgets->isEmpty()) {
            $this->error('No widgets found.');

            return self::FAILURE;
        }

        $this->line('');
        $this->line('Current default flow: '.count($flow['nodes']).' steps.');
        $this->line('');

        foreach ($widgets as $widget) {
            $current = count($widget->flow['nodes'] ?? []);
            $this->line(sprintf('  [%d] %-34s %d steps → %d', $widget->id, $widget->name, $current, count($flow['nodes'])));
        }

        if ($this->option('widget') === null) {
            $this->line('');
            $this->comment('Nothing changed. Re-run with --widget=<id> to apply.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->warn('This replaces the conversation entirely. Anything edited in the');
        $this->warn('builder for this widget will be lost.');

        if (! $this->option('force') && ! $this->confirm('Replace it?', false)) {
            $this->line('Nothing changed.');

            return self::SUCCESS;
        }

        $widget = $widgets->first();
        $widget->flow = $flow;
        $widget->save();

        $this->info('Replaced. Visitors already mid-conversation continue on the flow they started.');

        return self::SUCCESS;
    }
}
