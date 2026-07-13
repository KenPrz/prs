export type WorkflowStepType = 'APPROVAL' | 'DEPARTMENT_HEAD' | 'RECEIVE';

export type WorkflowAssignmentStatus =
    | 'PENDING'
    | 'APPROVED'
    | 'REJECTED'
    | 'RECEIVED'
    | 'REASSIGNED'
    | 'OVERRIDDEN'
    | 'STOPPED';

export type WorkflowStepStatus =
    | 'PENDING'
    | 'ACTIVE'
    | 'APPROVED'
    | 'RECEIVED'
    | 'REJECTED'
    | 'SKIPPED'
    | 'OVERRIDDEN'
    | 'STOPPED';

export type WorkflowAssignmentSummary = {
    id: number;
    user_id: number;
    user_name: string;
    status: WorkflowAssignmentStatus;
    source_type: string;
    overridden_by_name?: string | null;
    overridden_at?: string | null;
};

export type WorkflowStepSummary = {
    step_order: number;
    name: string;
    step_type: WorkflowStepType;
    completion_strategy: 'ALL' | 'ANY';
    status: WorkflowStepStatus;
    due_at?: string | null;
    assignments: WorkflowAssignmentSummary[];
};

export type WorkflowDecisionSummary = {
    actor_name: string;
    action:
        | 'APPROVE'
        | 'REJECT'
        | 'RECEIVE'
        | 'REASSIGN'
        | 'OVERRIDE'
        | 'CANCEL';
    comment: string | null;
    acted_at: string;
    is_override?: boolean;
};

export type WorkflowEventSummary = {
    event_type: string;
    label: string;
    actor_name: string | null;
    payload: Record<string, unknown> | null;
    created_at: string | null;
};

export type ApprovalSummary = {
    workflow_label: string;
    workflow_key: string;
    workflow_version: number;
    instance_status: 'PENDING' | 'APPROVED' | 'REJECTED' | 'CANCELLED';
    current_step_order: number | null;
    steps: WorkflowStepSummary[];
    decisions: WorkflowDecisionSummary[];
    history: WorkflowEventSummary[];
};
