import { ConfirmAction } from '@/components/confirm-action';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type CourseProp = {
    id: number;
    title: string;
    topic: string;
    description: string | null;
    is_published: boolean;
};

type LessonRow = {
    id: number;
    title: string;
    body: string | null;
    resource_url: string | null;
    completed: boolean;
};

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-24 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

export default function CourseShow({ course, lessons }: { course: CourseProp; lessons: LessonRow[] }) {
    const { can } = usePermissions();
    const canManage = can('strategy.manage');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Training', href: '/strategy/training' },
        { title: course.title, href: `/strategy/training/${course.id}` },
    ];

    const lessonForm = useForm<{ title: string; body: string; resource_url: string }>({ title: '', body: '', resource_url: '' });

    const addLesson: FormEventHandler = (e) => {
        e.preventDefault();
        lessonForm.transform((data) => ({ title: data.title, body: data.body || null, resource_url: data.resource_url || null }));
        lessonForm.post(route('strategy.training.lessons.store', course.id), { preserveScroll: true, onSuccess: () => lessonForm.reset() });
    };

    const toggle = (lesson: LessonRow) => {
        if (lesson.completed) {
            router.delete(route('strategy.training.uncomplete', lesson.id), { preserveScroll: true });
        } else {
            router.post(route('strategy.training.complete', lesson.id), {}, { preserveScroll: true });
        }
    };

    const completedCount = lessons.filter((lesson) => lesson.completed).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={course.title} />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <Heading title={course.title} description={course.description ?? ''} />
                        <div className="mt-1 flex flex-wrap items-center gap-2">
                            <Badge variant="outline">{course.topic}</Badge>
                            {!course.is_published && <Badge variant="secondary">Draft — only managers can see this</Badge>}
                            <span className="text-muted-foreground text-xs">
                                {completedCount} of {lessons.length} lessons complete
                            </span>
                        </div>
                    </div>
                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant={course.is_published ? 'outline' : 'default'}
                                onClick={() =>
                                    router.patch(
                                        route('strategy.training.update', course.id),
                                        { is_published: !course.is_published },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {course.is_published ? 'Unpublish' : 'Publish'}
                            </Button>
                            <ConfirmAction
                                title={`Delete "${course.title}"?`}
                                description="Its lessons and everyone's completion records go with it. This cannot be undone."
                                confirmLabel="Delete course"
                                onConfirm={() => router.delete(route('strategy.training.destroy', course.id))}
                            >
                                <Button variant="ghost" className="text-destructive">
                                    Delete
                                </Button>
                            </ConfirmAction>
                        </div>
                    )}
                </div>

                {lessons.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No lessons yet{canManage ? ' — add the first one below' : ''}.</p>
                ) : (
                    <div className="space-y-3">
                        {lessons.map((lesson, i) => (
                            <Card key={lesson.id}>
                                <CardContent className="p-4">
                                    <div className="flex items-start gap-3">
                                        <Checkbox
                                            id={`lesson-${lesson.id}`}
                                            checked={lesson.completed}
                                            onCheckedChange={() => toggle(lesson)}
                                            aria-label={`Mark "${lesson.title}" ${lesson.completed ? 'incomplete' : 'complete'}`}
                                        />
                                        <div className="min-w-0 flex-1 space-y-1">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <Label htmlFor={`lesson-${lesson.id}`} className="font-medium">
                                                    {i + 1}. {lesson.title}
                                                </Label>
                                                {canManage && (
                                                    <ConfirmAction
                                                        title={`Remove "${lesson.title}"?`}
                                                        description="Completion records for this lesson are removed with it."
                                                        confirmLabel="Remove"
                                                        onConfirm={() =>
                                                            router.delete(route('strategy.training.lessons.destroy', lesson.id), {
                                                                preserveScroll: true,
                                                            })
                                                        }
                                                    >
                                                        <Button size="sm" variant="ghost" className="text-destructive">
                                                            Remove
                                                        </Button>
                                                    </ConfirmAction>
                                                )}
                                            </div>
                                            {lesson.body && <p className="text-muted-foreground text-sm whitespace-pre-line">{lesson.body}</p>}
                                            {lesson.resource_url && (
                                                <a
                                                    href={lesson.resource_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-primary text-sm underline"
                                                >
                                                    Open resource
                                                </a>
                                            )}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {canManage && (
                    <Card>
                        <CardContent className="p-4">
                            <h3 className="mb-3 text-sm font-medium">Add lesson</h3>
                            <form onSubmit={addLesson} className="space-y-3">
                                <div className="grid gap-1">
                                    <Label htmlFor="lesson_title">Title</Label>
                                    <Input
                                        id="lesson_title"
                                        value={lessonForm.data.title}
                                        onChange={(e) => lessonForm.setData('title', e.target.value)}
                                    />
                                    <InputError message={lessonForm.errors.title} />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="lesson_body">Material</Label>
                                    <textarea
                                        id="lesson_body"
                                        className={textareaClass}
                                        value={lessonForm.data.body}
                                        onChange={(e) => lessonForm.setData('body', e.target.value)}
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="lesson_url">Resource URL (optional)</Label>
                                    <Input
                                        id="lesson_url"
                                        type="url"
                                        value={lessonForm.data.resource_url}
                                        onChange={(e) => lessonForm.setData('resource_url', e.target.value)}
                                    />
                                    <InputError message={lessonForm.errors.resource_url} />
                                </div>
                                <Button type="submit" variant="outline" disabled={lessonForm.processing}>
                                    Add lesson
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
