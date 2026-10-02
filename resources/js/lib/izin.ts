import {
    AlarmClock,
    LogOut,
    Palmtree,
    Siren,
    Stethoscope,
    Sunset,
    type LucideIcon,
} from 'lucide-react';

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

export const LEAVE_TYPE_ICONS: Record<LeaveType, LucideIcon> = {
    late: AlarmClock,
    half_day: Sunset,
    temporary_exit: LogOut,
    leave: Palmtree,
    emergency: Siren,
    sick: Stethoscope,
};

/** A user reference the way the backend serializes a loaded belongsTo
 *  relation (e.g. processedBy/hrdProcessedBy) — note this replaces the
 *  raw foreign-key column of the same snake_case name in the JSON
 *  output once that relation is eager-loaded, it isn't additive. */
export type LeaveRequestUserRef = { id: number; name: string } | null;
