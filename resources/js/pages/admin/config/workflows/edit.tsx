import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2, GripVertical, UserPlus } from 'lucide-react';
import ConfigFormSection from '@/components/admin-config/config-form-section';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import AdminConfigLayout from '@/layouts/admin-config/layout';
import AppLayout from '@/layouts/app-layout';
import type {
    BreadcrumbItem,
    UserOption,
    WorkflowConfig,
    WorkflowStepAssigneeConfig,
    WorkflowStepConfig,
} from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'System Configuration', href: '/admin/config/workflows' },
    { title: 'Workflows', href: '/admin/config/workflows' },
    { title: 'Edit', href: '#' },
];

const STEP_TYPES: { value: string; label: string }[] = [
    { value: 'APPROVAL', label: 'Approval' },
    { value: 'DEPARTMENT_HEAD', label: 'Department Head' },
    { value: 'RECEIVE', label: 'Receive' },
];

const ASSIGNEE_TYPES: { value: string; label: string }[] = [
    { value: 'ROLE', label: 'Role' },
    { value: 'USER', label: 'Specific User' },
    { value: 'PERMISSION', label: 'Permission' },
    { value: 'DEPARTMENT_HEAD', label: 'Department Head' },
    { value: 'RECEIVER', label: 'Receiver' },
    { value: 'CREATOR', label: 'Creator' },
];

const DYNAMIC_ASSIGNEES = ['DEPARTMENT_HEAD', 'RECEIVER', 'CREATOR'];

function emptyAssignee(): WorkflowStepAssigneeConfig {
    return {
        assignee_type: 'ROLE',
        assignee_identifier: null,
        user_id: null,
    };
}

function emptyStep(order: number): WorkflowStepConfig {
    return {
        step_order: order,
        name: '',
        step_type: 'APPROVAL',
        completion_strategy: 'ALL',
        sla_hours: null,
        condition: null,
        assignees: [emptyAssignee()],
    };
}

export default function WorkflowEdit({
    workflow,
    users,
    roleOptions,
    permissionOptions,
}: {
    workflow: WorkflowConfig;
    users: UserOption[];
    roleOptions: string[];
    permissionOptions: string[];
}) {
    const { data, setData, put, processing, errors } = useForm<{
        name: string;
        is_active: boolean;
        steps: WorkflowStepConfig[];
    }>({
        name: workflow.name,
        is_active: workflow.is_active,
        steps:
            workflow.steps && workflow.steps.length > 0
                ? workflow.steps.map((s) => ({
                      ...s,
                      assignees:
                          s.assignees.length > 0
                              ? s.assignees
                              : [emptyAssignee()],
                  }))
                : [emptyStep(1)],
    });

    function addStep() {
        setData('steps', [...data.steps, emptyStep(data.steps.length + 1)]);
    }

    function removeStep(index: number) {
        if (data.steps.length <= 1) {
            return;
        }

        const updated = data.steps
            .filter((_, i) => i !== index)
            .map((s, i) => ({ ...s, step_order: i + 1 }));
        setData('steps', updated);
    }

    function updateStep(
        index: number,
        field: keyof WorkflowStepConfig,
        value: unknown,
    ) {
        const updated = [...data.steps];
        updated[index] = { ...updated[index], [field]: value };
        setData('steps', updated);
    }

    function addAssignee(stepIndex: number) {
        const updated = [...data.steps];
        updated[stepIndex] = {
            ...updated[stepIndex],
            assignees: [...updated[stepIndex].assignees, emptyAssignee()],
        };
        setData('steps', updated);
    }

    function removeAssignee(stepIndex: number, assigneeIndex: number) {
        const updated = [...data.steps];
        const step = updated[stepIndex];

        if (step.assignees.length <= 1) {
            return;
        }

        updated[stepIndex] = {
            ...step,
            assignees: step.assignees.filter((_, i) => i !== assigneeIndex),
        };
        setData('steps', updated);
    }

    function updateAssignee(
        stepIndex: number,
        assigneeIndex: number,
        field: keyof WorkflowStepAssigneeConfig,
        value: unknown,
    ) {
        const updated = [...data.steps];
        const step = updated[stepIndex];
        const assignees = [...step.assignees];
        assignees[assigneeIndex] = {
            ...assignees[assigneeIndex],
            [field]: value,
        };
        updated[stepIndex] = { ...step, assignees };
        setData('steps', updated);
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/config/workflows/${workflow.id}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${workflow.name} — Configuration`} />
            <AdminConfigLayout>
                <form onSubmit={handleSubmit} className="space-y-6">
                    <ConfigFormSection
                        title="Edit Workflow"
                        description={`Update workflow "${workflow.name}".`}
                    >
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Key</Label>
                                <p className="rounded-md border border-border bg-muted/40 px-3 py-2 font-mono text-sm">
                                    {workflow.key}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    The key is how the system resolves this
                                    workflow — it cannot be changed.
                                </p>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="grid gap-2">
                                <Label>Document Type</Label>
                                <p className="rounded-md border border-border bg-muted/40 px-3 py-2 text-sm">
                                    {workflow.document_type}
                                </p>
                            </div>
                            <div className="grid gap-2">
                                <Label>Version</Label>
                                <p className="rounded-md border border-border bg-muted/40 px-3 py-2 text-sm">
                                    v{workflow.version} (increments on save)
                                </p>
                            </div>
                            <div className="flex items-end gap-2 pb-1">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) =>
                                        setData('is_active', checked === true)
                                    }
                                />
                                <Label
                                    htmlFor="is_active"
                                    className="cursor-pointer"
                                >
                                    Active
                                </Label>
                            </div>
                        </div>
                    </ConfigFormSection>

                    {/* Steps Builder */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Workflow Steps</CardTitle>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addStep}
                            >
                                <Plus className="mr-1.5 h-4 w-4" />
                                Add Step
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {data.steps.map((step, stepIndex) => (
                                <div
                                    key={stepIndex}
                                    className="space-y-4 rounded-lg border border-border/60 bg-muted/20 p-4"
                                >
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <GripVertical className="h-4 w-4 text-muted-foreground" />
                                            <Badge
                                                variant="outline"
                                                className="text-xs"
                                            >
                                                Step {stepIndex + 1}
                                            </Badge>
                                            {step.id && (
                                                <span className="text-[10px] text-muted-foreground">
                                                    ID: {step.id}
                                                </span>
                                            )}
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                removeStep(stepIndex)
                                            }
                                            disabled={data.steps.length <= 1}
                                            className="text-destructive hover:text-destructive"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>

                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                        <div className="grid gap-2">
                                            <Label>Step Name</Label>
                                            <Input
                                                value={step.name}
                                                onChange={(e) =>
                                                    updateStep(
                                                        stepIndex,
                                                        'name',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `steps.${stepIndex}.name` as keyof typeof errors
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label>Step Type</Label>
                                            <Select
                                                value={step.step_type}
                                                onValueChange={(v) =>
                                                    updateStep(
                                                        stepIndex,
                                                        'step_type',
                                                        v,
                                                    )
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {STEP_TYPES.map((t) => (
                                                        <SelectItem
                                                            key={t.value}
                                                            value={t.value}
                                                        >
                                                            {t.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label>Completion Strategy</Label>
                                            <Select
                                                value={step.completion_strategy}
                                                onValueChange={(v) =>
                                                    updateStep(
                                                        stepIndex,
                                                        'completion_strategy',
                                                        v,
                                                    )
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="ALL">
                                                        All must approve
                                                    </SelectItem>
                                                    <SelectItem value="ANY">
                                                        Any one can approve
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label>SLA (hours, optional)</Label>
                                            <Input
                                                type="number"
                                                min={1}
                                                value={step.sla_hours ?? ''}
                                                onChange={(e) =>
                                                    updateStep(
                                                        stepIndex,
                                                        'sla_hours',
                                                        e.target.value
                                                            ? parseInt(
                                                                  e.target
                                                                      .value,
                                                              )
                                                            : null,
                                                    )
                                                }
                                                placeholder="e.g. 48 — escalate if overdue"
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label>
                                                Condition (JSON, optional)
                                            </Label>
                                            <Input
                                                value={
                                                    step.condition
                                                        ? JSON.stringify(
                                                              step.condition,
                                                          )
                                                        : ''
                                                }
                                                onChange={(e) => {
                                                    const raw =
                                                        e.target.value.trim();
                                                    let parsed: WorkflowStepConfig['condition'] =
                                                        null;

                                                    try {
                                                        parsed = raw
                                                            ? JSON.parse(raw)
                                                            : null;
                                                    } catch {
                                                        parsed =
                                                            step.condition ??
                                                            null;
                                                    }

                                                    updateStep(
                                                        stepIndex,
                                                        'condition',
                                                        parsed,
                                                    );
                                                }}
                                                placeholder='e.g. {"gross_total":{"gte":50000}}'
                                            />
                                        </div>
                                    </div>

                                    <Separator />

                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between">
                                            <Label className="text-xs tracking-wider text-muted-foreground uppercase">
                                                Assignees
                                            </Label>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    addAssignee(stepIndex)
                                                }
                                            >
                                                <UserPlus className="mr-1 h-3.5 w-3.5" />
                                                Add
                                            </Button>
                                        </div>
                                        {step.assignees.map(
                                            (assignee, aIdx) => (
                                                <div
                                                    key={aIdx}
                                                    className="flex items-center gap-2"
                                                >
                                                    <Select
                                                        value={
                                                            assignee.assignee_type
                                                        }
                                                        onValueChange={(v) =>
                                                            updateAssignee(
                                                                stepIndex,
                                                                aIdx,
                                                                'assignee_type',
                                                                v,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger className="w-[150px]">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {ASSIGNEE_TYPES.map(
                                                                (t) => (
                                                                    <SelectItem
                                                                        key={
                                                                            t.value
                                                                        }
                                                                        value={
                                                                            t.value
                                                                        }
                                                                    >
                                                                        {
                                                                            t.label
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>

                                                    {assignee.assignee_type ===
                                                    'USER' ? (
                                                        <Combobox
                                                            className="flex-1"
                                                            placeholder="Select user…"
                                                            searchPlaceholder="Search users…"
                                                            emptyText="No users found."
                                                            value={
                                                                assignee.user_id?.toString() ??
                                                                ''
                                                            }
                                                            onChange={(v) =>
                                                                updateAssignee(
                                                                    stepIndex,
                                                                    aIdx,
                                                                    'user_id',
                                                                    parseInt(v),
                                                                )
                                                            }
                                                            options={users.map(
                                                                (u) => ({
                                                                    value: u.id.toString(),
                                                                    label: `${u.name} (${u.email})`,
                                                                }),
                                                            )}
                                                        />
                                                    ) : DYNAMIC_ASSIGNEES.includes(
                                                          assignee.assignee_type,
                                                      ) ? (
                                                        <p className="flex-1 text-xs text-muted-foreground italic">
                                                            Resolved
                                                            automatically from
                                                            the document.
                                                        </p>
                                                    ) : (
                                                        <Select
                                                            value={
                                                                assignee.assignee_identifier ??
                                                                ''
                                                            }
                                                            onValueChange={(
                                                                v,
                                                            ) =>
                                                                updateAssignee(
                                                                    stepIndex,
                                                                    aIdx,
                                                                    'assignee_identifier',
                                                                    v,
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger className="flex-1">
                                                                <SelectValue
                                                                    placeholder={
                                                                        assignee.assignee_type ===
                                                                        'PERMISSION'
                                                                            ? 'Select permission…'
                                                                            : 'Select role…'
                                                                    }
                                                                />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {(assignee.assignee_type ===
                                                                'PERMISSION'
                                                                    ? permissionOptions
                                                                    : roleOptions
                                                                ).map(
                                                                    (name) => (
                                                                        <SelectItem
                                                                            key={
                                                                                name
                                                                            }
                                                                            value={
                                                                                name
                                                                            }
                                                                        >
                                                                            {
                                                                                name
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                    )}

                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            removeAssignee(
                                                                stepIndex,
                                                                aIdx,
                                                            )
                                                        }
                                                        disabled={
                                                            step.assignees
                                                                .length <= 1
                                                        }
                                                        className="shrink-0 text-destructive hover:text-destructive"
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </Button>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving…' : 'Save Changes'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/admin/config/workflows">Cancel</Link>
                        </Button>
                    </div>
                </form>
            </AdminConfigLayout>
        </AppLayout>
    );
}
