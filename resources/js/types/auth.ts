export type User = {
    id: number;
    company_id: number;
    position_id: number;
    employee_number: string | null;
    name: string;
    email: string;
    phone: string | null;
    role: 'karyawan' | 'hrd' | 'admin' | 'finance';
    status: 'pending' | 'active' | 'rejected';
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
