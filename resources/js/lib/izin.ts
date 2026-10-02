export type LeaveType =
    | 'late'
    | 'half_day'
    | 'temporary_exit'
    | 'leave'
    | 'emergency'
    | 'sick';

export const LEAVE_TYPES: LeaveType[] = [
    'late',
    'half_day',
    'temporary_exit',
    'leave',
    'emergency',
    'sick',
];

export const LEAVE_TYPE_LABELS: Record<LeaveType, string> = {
    late: 'Izin Terlambat',
    half_day: 'Izin Setengah Hari',
    temporary_exit: 'Izin Keluar Kantor Sebentar',
    leave: 'Cuti',
    emergency: 'Izin Darurat',
    sick: 'Izin Sakit',
};
