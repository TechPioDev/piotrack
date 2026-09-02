import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Loader2 } from 'lucide-react';
import { ReactNode, useState } from 'react';

/**
 * The confirmation standard for destructive actions (DSGN-008): nothing
 * irreversible fires from a single click. The trigger opens a dialog that
 * names exactly what is about to happen; only the explicit confirm button
 * runs the action, and the dialog closes itself when it does.
 *
 * Usage:
 *   <ConfirmAction
 *       title="Delete this keyword?"
 *       description="Rank history for it is removed with it."
 *       confirmLabel="Delete"
 *       onConfirm={() => router.delete(...)}
 *   >
 *       <Button variant="ghost">Delete</Button>
 *   </ConfirmAction>
 */
export function ConfirmAction({
    title,
    description,
    confirmLabel = 'Confirm',
    processing = false,
    onConfirm,
    children,
}: {
    title: string;
    description: string;
    confirmLabel?: string;
    processing?: boolean;
    onConfirm: () => void;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);

    const confirm = () => {
        onConfirm();
        setOpen(false);
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>
                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button variant="destructive" onClick={confirm} disabled={processing}>
                        {processing && <Loader2 className="mr-2 size-4 animate-spin" />}
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
