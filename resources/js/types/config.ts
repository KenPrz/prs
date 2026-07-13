export type WorkflowAssigneeType =
    | 'USER'
    | 'ROLE'
    | 'PERMISSION'
    | 'DEPARTMENT_HEAD'
    | 'RECEIVER'
    | 'CREATOR';

type ConditionValue = string | number | boolean | Array<string | number>;

export type WorkflowCondition = Record<string, Record<string, ConditionValue>>;

export type WorkflowStepAssigneeConfig = {
    id?: number;
    assignee_type: WorkflowAssigneeType | string;
    assignee_identifier: string | null;
    user_id: number | null;
    user?: { id: number; name: string; email: string } | null;
};

export type WorkflowStepConfig = {
    id?: number;
    step_order: number;
    name: string;
    step_type: string;
    completion_strategy: string;
    sla_hours?: number | null;
    condition?: WorkflowCondition | null;
    is_active?: boolean;
    assignees: WorkflowStepAssigneeConfig[];
};

export type WorkflowConfig = {
    id: number;
    key: string;
    name: string;
    document_type: string;
    version: number;
    is_active: boolean;
    steps_count?: number;
    steps?: WorkflowStepConfig[];
    created_at: string;
    updated_at: string;
};

export type AddressConfig = {
    id?: number;
    recipient_name: string | null;
    street: string | null;
    barangay: string | null;
    city: string | null;
    province: string | null;
    zip_code: string | null;
    mobile_no: string | null;
};

export type CompanyProfileConfig = {
    id: number;
    name: string;
    email: string | null;
    mobile_no: string | null;
    tin: string | null;
    currency: string | null;
    default_received_by_user_id: number | null;
    address?: AddressConfig | null;
};

export type DepartmentConfig = {
    id: number;
    name: string;
    code: string;
    department_head_id: number | null;
    department_head?: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
};

export type SupplierConfig = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    contact_person_1: string | null;
    contact_person_2: string | null;
    contact_person_3: string | null;
    created_at: string;
    updated_at: string;
};

export type ItemUnitConfig = {
    id: number;
    code: string;
    name: string;
    created_at: string;
    updated_at: string;
};

export type DocumentConfig = {
    id: number;
    resource_class: string;
    resource_label: string;
    blade_file: string;
    created_at: string;
    updated_at: string;
};

export type ResourceClassOption = {
    value: string;
    label: string;
};

export type UserOption = {
    id: number;
    name: string;
    email: string;
};

export type PermissionConfig = {
    id: number;
    name: string;
    guard_name: string;
    roles_count?: number;
};

export type RoleConfig = {
    id: number;
    name: string;
    guard_name: string;
    users_count?: number;
    permissions?: PermissionConfig[];
};

export type UserConfig = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    roles?: RoleConfig[];
    departments?: DepartmentConfig[];
    created_at: string;
    updated_at: string;
};
