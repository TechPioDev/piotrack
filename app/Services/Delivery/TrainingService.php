<?php

namespace App\Services\Delivery;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Collection;

/**
 * The course surface behind Training & Consulting (TRAIN-001..007): progress
 * math and the starter curriculum. Teaching stays human-delivered; this is
 * where its materials live and completion is measured.
 */
class TrainingService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Per-course progress for one user: completed vs total lessons.
     *
     * @param  Collection<int, Course>  $courses
     * @return array<int, array{total: int, completed: int, pct: int}>
     */
    public function progress($courses, User $user): array
    {
        $lessons = Lesson::whereIn('course_id', $courses->pluck('id'))->get(['id', 'course_id'])->groupBy('course_id');
        $completed = LessonCompletion::where('user_id', $user->id)->pluck('lesson_id')->flip();

        $out = [];
        foreach ($courses as $course) {
            $ids = $lessons->get($course->id, collect())->pluck('id');
            $done = $ids->filter(fn (int $id) => isset($completed[$id]))->count();
            $out[$course->id] = [
                'total' => $ids->count(),
                'completed' => $done,
                'pct' => $ids->isNotEmpty() ? (int) round($done / $ids->count() * 100) : 0,
            ];
        }

        return $out;
    }

    /**
     * Idempotent starter curriculum: real, platform-anchored training material
     * (the same discipline as the MSP keyword library — domain knowledge, not
     * fabricated data). Arrives unpublished so a manager reviews and publishes
     * deliberately. Existing courses with the same title are left untouched.
     *
     * @return int newly created courses
     */
    public function seedCurriculum(): int
    {
        $created = 0;

        foreach ($this->curriculum() as $courseData) {
            if (Course::where('title', $courseData['title'])->exists()) {
                continue;
            }

            $course = Course::create([
                'title' => $courseData['title'],
                'topic' => $courseData['topic'],
                'description' => $courseData['description'],
                'is_published' => false,
            ]);

            foreach ($courseData['lessons'] as $i => $lesson) {
                Lesson::create([
                    'course_id' => $course->id,
                    'title' => $lesson['title'],
                    'body' => $lesson['body'],
                    'sort_order' => $i + 1,
                ]);
            }

            $created++;
        }

        if ($created > 0) {
            $this->audit->log('training.curriculum.seeded', context: ['courses' => $created]);
        }

        return $created;
    }

    /**
     * @return list<array{title: string, topic: string, description: string, lessons: list<array{title: string, body: string}>}>
     */
    private function curriculum(): array
    {
        return [
            [
                'title' => 'Marketing operations in Piotrack',
                'topic' => 'marketing',
                'description' => 'How your team runs the marketing engine this platform tracks: campaigns, forms, automation and attribution.',
                'lessons' => [
                    ['title' => 'From form to contact: the capture chain', 'body' => "Every public form submission becomes a contact with a source, joins the form's target list, starts the matching automation workflow, and notifies the owners. Walk the chain once with a test submission on one of your published forms, then find the audit entry (lead.captured) and the workflow enrolment it produced — knowing this chain is how you debug 'where did this lead come from?' in seconds."],
                    ['title' => 'Channels and attribution you can trust', 'body' => "Piotrack classifies every visitor's first touch into organic, paid, social, content, referral or direct from UTM parameters and the referrer — never from guesswork. The channel report on Strategy only counts leads whose channel was actually observed. Practice: open Strategy → lead channels and identify which channel produced your last five SQLs."],
                    ['title' => 'Reading the Command Center honestly', 'body' => "KPI deltas compare the selected window against the one before it; a 'new' badge means the previous window was zero, not infinite growth. Pipeline and ARR carry no delta because they are stocks, not flows — a delta on them would be a lie. Set the range to 60 days and explain each KPI's movement to yourself before your next marketing review."],
                ],
            ],
            [
                'title' => 'SEO practice for MSPs',
                'topic' => 'seo',
                'description' => 'Running the SEO module end to end: keywords, rank tracking, technical crawls and local presence.',
                'lessons' => [
                    ['title' => 'The keyword library and what to track', 'body' => 'Seed the MSP keyword library (Keywords → Seed MSP library) and you get curated, typed and intent-classified seeds — untracked, with no invented volumes. Track only keywords you have a page or plan for; the head-to-head list shows where competitors outrank you on keywords you both target. Volumes stay empty until a research provider is connected: an empty cell is honest, a guessed one is not.'],
                    ['title' => 'Technical crawls that produce work lists', 'body' => "A site crawl (SEO → Audit → Crawl site) walks up to 20 pages and reports ten sections: indexation, robots, sitemap gaps, orphans, duplicates, broken links, redirects and more. Every finding names its page and its fix — treat the report as this sprint's SEO backlog, re-crawl after shipping fixes, and watch the findings count fall."],
                    ['title' => 'Local presence: NAP, citations, location pages', 'body' => 'Each branch needs a complete NAP record, a published location page naming its market, and consistent citations — the local report flags exactly which branch is missing which. Generate a location page draft from the branch record, review the copy, publish, and re-check the branch report.'],
                ],
            ],
            [
                'title' => 'Sales process on the pipeline',
                'topic' => 'sales',
                'description' => 'Working leads the way the scoring and intent engines expect: fast follow-up on real signals.',
                'lessons' => [
                    ['title' => 'What the lead score actually says', 'body' => 'Scores come from rules you can read (Settings → scoring): title and firmographic matches plus weighted behavioural intent — pricing views, repeat visits, form submissions, email clicks. A lead crosses MQL at 20 and SQL at higher thresholds automatically. Open your five newest MQLs and check which rule fired for each; if you disagree with the rule, change the rule, not the score.'],
                    ['title' => 'Acting on intent signals and alerts', 'body' => "A known contact returning to the site, hitting the pricing page, or reading three pieces of content fires a sales alert — those are the 'call them now' moments, and the buying-window badge means three or more signals in fourteen days. Work alerts the day they fire: intent decays faster than any other lead attribute."],
                    ['title' => 'Round-robin ownership and keeping the CRM honest', 'body' => 'Every captured lead is auto-assigned to the least-loaded active member, so there is no unowned-lead pile. Log calls, emails and meetings as activities — logged activity is itself an intent signal, and the CRM-hygiene analysis on Strategy names stale deals and contactless accounts every week.'],
                ],
            ],
            [
                'title' => 'Executive strategy reviews',
                'topic' => 'executive',
                'description' => 'Running QBRs and strategy reviews from the numbers the platform already keeps.',
                'lessons' => [
                    ['title' => 'Preparing a QBR from real records', 'body' => 'Book the QBR as an engagement (Strategy → engagements) so it is tracked like any other commitment. The materials write themselves: Command Center for the quarter\'s KPIs, Strategy insights for funnel/channel/vertical analyses, and the growth-score history for trajectory. If a number is not in the platform, it does not go on the slide.'],
                    ['title' => 'The revenue model and its honesty guards', 'body' => "The revenue model refuses to project from fewer than five closed deals, and the ICP profile refuses to exist with zero wins — a guard saying 'not enough data' is a finding, not a failure. Review which analyses are unlocked for your tenant and what closing the next five deals would unlock."],
                    ['title' => 'Turning reviews into tracked commitments', 'body' => 'Every review ends as strategy items with owners and quarters — otherwise it was a conversation, not a review. Create the items during the meeting, and start the next review from the last quarter\'s item list: done, moved, or dropped, each with a reason.'],
                ],
            ],
        ];
    }
}
