import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ConfirmDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    destructive?: boolean;
    confirming?: boolean;
    onConfirm: () => void;
};

/**
 * Replaces the browser's native confirm() with a dialog styled like the
 * rest of the app, so every "are you sure?" moment looks the same
 * regardless of which screen it's on.
 */
export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = 'Lanjutkan',
    cancelLabel = 'Batal',
    destructive = false,
    confirming = false,
    onConfirm,
}: ConfirmDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        className="rounded-full"
                        onClick={() => onOpenChange(false)}
                        disabled={confirming}
                    >
                        {cancelLabel}
                    </Button>
                    <Button
                        type="button"
                        variant={destructive ? 'destructive' : 'default'}
                        className="rounded-full"
                        onClick={onConfirm}
                        disabled={confirming}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
