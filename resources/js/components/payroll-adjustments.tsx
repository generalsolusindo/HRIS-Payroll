import { Form } from '@inertiajs/react';
import { useState } from 'react';
import PayrollAdjustmentController from '@/actions/App/Http/Controllers/Hrd/PayrollAdjustmentController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ManualLine = {
    id: number;
    source: string;
    component_name: string;
    cancelled: boolean;
};

export function PayrollAdjustments({
    entryId,
    employeeName,
    lines,
}: {
    entryId: number;
    employeeName: string;
    lines: ManualLine[];
}) {
    const [open, setOpen] = useState(false);
    const [cancelLine, setCancelLine] = useState<ManualLine | null>(null);
    const [requestId, setRequestId] = useState('');
    return (
        <div className="mt-5 space-y-3 border-t pt-4">
            <Button
                variant="outline"
                onClick={() => {
                    setRequestId(crypto.randomUUID());
                    setCancelLine(null);
                    setOpen(true);
                }}
            >
                Tambah penyesuaian manual
            </Button>
            <p className="text-xs text-muted-foreground">
                Hanya untuk draft. Setiap perubahan memerlukan alasan dan
                tercatat dalam audit.
            </p>
            {lines
                .filter((line) => line.source === 'manual' && !line.cancelled)
                .map((line) => (
                    <div
                        key={line.id}
                        className="flex items-center justify-between gap-3 text-xs"
                    >
                        <span className="min-w-0 break-words">
                            {line.component_name}
                        </span>
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => {
                                setCancelLine(line);
                                setOpen(true);
                            }}
                        >
                            Batalkan
                        </Button>
                    </div>
                ))}
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {cancelLine
                                ? 'Batalkan penyesuaian'
                                : 'Penyesuaian gaji'}
                        </DialogTitle>
                        <DialogDescription>
                            {employeeName} —{' '}
                            {cancelLine
                                ? `Nominal ${cancelLine.component_name} akan dinolkan; jejak perubahan tetap disimpan.`
                                : 'Masukkan pendapatan tambahan atau potongan. Gaji bersih tidak boleh menjadi negatif.'}
                        </DialogDescription>
                    </DialogHeader>
                    {open && (
                        <Form
                            key={cancelLine?.id ?? requestId}
                            {...(cancelLine
                                ? PayrollAdjustmentController.destroy.form(
                                      cancelLine.id,
                                  )
                                : PayrollAdjustmentController.store.form(
                                      entryId,
                                  ))}
                            disableWhileProcessing
                            onSuccess={() => setOpen(false)}
                        >
                            {({ processing, errors }) => (
                                <div className="space-y-4">
                                    {!cancelLine && (
                                        <>
                                            <input
                                                type="hidden"
                                                name="request_id"
                                                value={requestId}
                                            />
                                            <div className="space-y-2">
                                                <Label htmlFor="adjustment-type">
                                                    Jenis
                                                </Label>
                                                <select
                                                    id="adjustment-type"
                                                    name="type"
                                                    className="h-10 w-full rounded-lg border bg-background px-3 text-sm"
                                                    defaultValue="earning"
                                                >
                                                    <option value="earning">
                                                        Tambahan pendapatan
                                                    </option>
                                                    <option value="deduction">
                                                        Potongan
                                                    </option>
                                                </select>
                                                <InputError
                                                    message={errors.type}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="adjustment-name">
                                                    Nama komponen
                                                </Label>
                                                <Input
                                                    id="adjustment-name"
                                                    name="name"
                                                    maxLength={150}
                                                    required
                                                    placeholder="Contoh: bonus proyek"
                                                />
                                                <InputError
                                                    message={errors.name}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="adjustment-amount">
                                                    Nominal (Rp)
                                                </Label>
                                                <Input
                                                    id="adjustment-amount"
                                                    name="amount"
                                                    type="number"
                                                    inputMode="decimal"
                                                    min="0.01"
                                                    max="999999999999.99"
                                                    step="0.01"
                                                    required
                                                />
                                                <InputError
                                                    message={errors.amount}
                                                />
                                            </div>
                                        </>
                                    )}
                                    <div className="space-y-2">
                                        <Label htmlFor="adjustment-reason">
                                            Alasan{' '}
                                            {cancelLine
                                                ? 'pembatalan'
                                                : 'penyesuaian'}
                                        </Label>
                                        <Input
                                            id="adjustment-reason"
                                            name="reason"
                                            maxLength={2000}
                                            required
                                        />
                                        <InputError message={errors.reason} />
                                    </div>
                                    {Object.keys(errors).length > 0 && (
                                        <div
                                            role="alert"
                                            className="space-y-1 text-sm text-destructive"
                                        >
                                            {Object.entries(errors)
                                                .filter(
                                                    ([field]) =>
                                                        ![
                                                            'name',
                                                            'type',
                                                            'reason',
                                                            ...(!cancelLine
                                                                ? ['amount']
                                                                : []),
                                                        ].includes(field),
                                                )
                                                .map(([field, message]) => (
                                                    <p key={field}>{message}</p>
                                                ))}
                                        </div>
                                    )}
                                    <DialogFooter>
                                        <Button
                                            variant="outline"
                                            type="button"
                                            disabled={processing}
                                            onClick={() => setOpen(false)}
                                        >
                                            Tutup
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Menyimpan…'
                                                : cancelLine
                                                  ? 'Batalkan penyesuaian'
                                                  : 'Simpan penyesuaian'}
                                        </Button>
                                    </DialogFooter>
                                </div>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
