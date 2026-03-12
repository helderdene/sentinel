export type UserRole =
    | 'admin'
    | 'dispatcher'
    | 'operator'
    | 'responder'
    | 'supervisor';

export type UserPermissions = {
    manage_users: boolean;
    manage_incident_types: boolean;
    manage_barangays: boolean;
    create_incidents: boolean;
    dispatch_units: boolean;
    respond_incidents: boolean;
    view_analytics: boolean;
    view_all_incidents: boolean;
    manage_system: boolean;
    triage_incidents: boolean;
    manual_entry: boolean;
    submit_dispatch: boolean;
    override_priority: boolean;
    recall_incident: boolean;
    view_session_log: boolean;
};

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role: UserRole;
    can: UserPermissions;
    email_verified_at: string | null;
};

export type Auth = {
    user: User;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
