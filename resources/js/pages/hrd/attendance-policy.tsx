import { Form, Head } from '@inertiajs/react';
import { Landmark } from 'lucide-react';
import PayrollPolicyController from '@/actions/App/Http/Controllers/Hrd/PayrollPolicyController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type PolicyRow = {
    id: number;
    company: { id: number; name: string };
    late_deduction_per_minute: string;
    absence_deduction_per_day: string;
    perfect_attendance_reward: string;
};

type Props = {
    policies: PolicyRow[];
};

export default function AttendancePolicy({ policies }: Props) {
    return (
        <>
            <Head title="Kebijakan Absensi" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        Kebijakan Absensi
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Tarif ini dipakai kalkulator payroll untuk menghitung
                        potongan keterlambatan/alpha dan reward kehadiran
                        sempurna dari data Koreksi Absensi. Kosongkan (isi 0)
                        untuk menonaktifkan salah satu — hasil payroll yang
                        sudah dihitung sebelumnya tidak berubah.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {policies.map((policy) => (
                        <Form
                            key={policy.id}
                            {...PayrollPolicyController.update.form(policy.id)}
                            disableWhileProcessing
                        >
                            {({ processing, errors, recentlySuccessful }) => (
                                <div className="flex flex-col gap-3 rounded-2xl border p-4">
                                    <div className="flex items-center gap-2 font-semibold">
                                        <Landmark className="size-4 text-muted-foreground" />
                                        {policy.company.name}
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor={`late-${policy.id}`}
                                            className="text-xs"
                                        >
                                            Potongan telat (Rp/menit)
                                        </Label>
                                        <Input
                                            id={`late-${policy.id}`}
                                            name="late_deduction_per_minute"
                                            type="number"
                                            min="0"
                                            step="1"
                                            defaultValue={
                                                policy.late_deduction_per_minute
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors.late_deduction_per_minute
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor={`absence-${policy.id}`}
                                            className="text-xs"
                                        >
                                            Potongan alpha (Rp/hari)
                                        </Label>
                                        <Input
                                            id={`absence-${policy.id}`}
                                            name="absence_deduction_per_day"
                                            type="number"
                                            min="0"
                                            step="1"
                                            defaultValue={
                                                policy.absence_deduction_per_day
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors.absence_deduction_per_day
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor={`reward-${policy.id}`}
                                            className="text-xs"
                                        >
                                            Reward kehadiran sempurna (Rp)
                                        </Label>
                                        <Input
                                            id={`reward-${policy.id}`}
                                            name="perfect_attendance_reward"
                                            type="number"
                                            min="0"
                                            step="1"
                                            defaultValue={
                                                policy.perfect_attendance_reward
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors.perfect_attendance_reward
                                            }
                                        />
                                    </div>

                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={processing}
                                        className="mt-1 rounded-full"
                                    >
                                        {recentlySuccessful
                                            ? 'Tersimpan'
                                            : 'Simpan'}
                                    </Button>
                                </div>
                            )}
                        </Form>
                    ))}
                </div>
            </div>
        </>
    );
}

AttendancePolicy.layout = {
    breadcrumbs: [
        {
            title: 'Kebijakan Absensi',
            href: PayrollPolicyController.index(),
        },
    ],
};
