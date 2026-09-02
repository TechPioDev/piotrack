import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Training', href: '/strategy/training' }];

type CourseRow = {
    id: number;
    title: string;
    topic: string;
    description: string | null;
    is_published: boolean;
    progress: { total: number; completed: number; pct: number };
};

const TOPIC_LABELS: Record<string, string> = {
    marketing: 'Marketing',
    seo: 'SEO',
    sales: 'Sales',
    executive: 'Executive strategy',
};

export default function Training({ topics, courses }: { topics: string[]; courses: CourseRow[] }) {
    const { can } = usePermissions();
    const canManage = can('strategy.manage');
    const [topicFilter, setTopicFilter] = useState<string>('all');

    const form = useForm<{ title: string; topic: string; description: string }>({ title: '', topic: topics[0] ?? '', description: '' });

    const create: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('strategy.training.store'));
    };

    const visible = topicFilter === 'all' ? courses : courses.filter((course) => course.topic === topicFilter);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Training" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-2">
                    <Heading
                        title="Training"
                        description="Course materials behind your consulting and training engagements, with completion tracking"
                    />
                    {canManage && (
                        <Button variant="secondary" onClick={() => router.post(route('strategy.training.seed'), {}, { preserveScroll: true })}>
                            Seed starter curriculum
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button size="sm" variant={topicFilter === 'all' ? 'default' : 'outline'} onClick={() => setTopicFilter('all')}>
                        All
                    </Button>
                    {topics.map((topic) => (
                        <Button key={topic} size="sm" variant={topicFilter === topic ? 'default' : 'outline'} onClick={() => setTopicFilter(topic)}>
                            {TOPIC_LABELS[topic] ?? topic}
                        </Button>
                    ))}
                </div>

                {visible.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No courses here yet.{canManage ? ' Seed the starter curriculum or create a course below.' : ''}
                    </p>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {visible.map((course) => (
                            <Card key={course.id} className="min-w-0">
                                <CardContent className="space-y-2 p-4">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant="outline">{TOPIC_LABELS[course.topic] ?? course.topic}</Badge>
                                        {!course.is_published && <Badge variant="secondary">Draft</Badge>}
                                    </div>
                                    <Link href={route('strategy.training.show', course.id)} className="block font-medium hover:underline">
                                        {course.title}
                                    </Link>
                                    {course.description && <p className="text-muted-foreground text-sm">{course.description}</p>}
                                    <div className="space-y-1">
                                        <div className="bg-muted h-2 overflow-hidden rounded-full">
                                            <div className="bg-primary h-full rounded-full" style={{ width: `${course.progress.pct}%` }} />
                                        </div>
                                        <p className="text-muted-foreground text-xs">
                                            {course.progress.completed} of {course.progress.total} lessons complete
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {canManage && (
                    <Card>
                        <CardContent className="p-4">
                            <h3 className="mb-3 text-sm font-medium">New course</h3>
                            <form onSubmit={create} className="flex flex-wrap items-end gap-2">
                                <div className="grid min-w-56 flex-1 gap-1">
                                    <Label htmlFor="course_title">Title</Label>
                                    <Input id="course_title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                                    <InputError message={form.errors.title} />
                                </div>
                                <div className="grid gap-1">
                                    <Label>Topic</Label>
                                    <Select value={form.data.topic} onValueChange={(v) => form.setData('topic', v)}>
                                        <SelectTrigger className="w-44">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {topics.map((topic) => (
                                                <SelectItem key={topic} value={topic}>
                                                    {TOPIC_LABELS[topic] ?? topic}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button type="submit" disabled={form.processing}>
                                    Create course
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
