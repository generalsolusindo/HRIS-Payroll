import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type PromptDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    label: string;
    placeholder?: string;
    submitLabel?: string;
    submitting?: boolean;
    onSubmit: (value: string) => void;
};

/**
 * Replaces the browser's native prompt() with a dialog styled like the
 * rest of the app — same idea as ConfirmDialog, but for the few actions
 * that need a short text reason before proceeding.
 */
export function PromptDialog({
    open,
    onOpenChange,
    title,
    description,
    label,
    placeholder,
    submitLabel = 'Kirim',
    submitting = false,
    onSubmit,
}: PromptDialogProps) {
    const [value, setValue] = useState('');

    function handleOpenChange(next: boolean) {
        if (!next) {
            setValue('');
        }
        onOpenChange(next);
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>
                <div className="grid gap-1.5">
                    <Label htmlFor="prompt-dialog-value">{label}</Label>
                    <Textarea
                        id="prompt-dialog-value"
                        value={value}
                        onChange={(e) => setValue(e.target.value)}
                        placeholder={placeholder}
                    />
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        className="rounded-full"
                        onClick={() => handleOpenChange(false)}
                        disabled={submitting}
                    >
                        Batal
                    </Button>
                    <Button
                        type="button"
                        className="rounded-full"
                        disabled={!value.trim() || submitting}
                        onClick={() => onSubmit(value.trim())}
                    >
                        {submitLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
