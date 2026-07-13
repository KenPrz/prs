import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import type { ComboboxOption } from '@/components/ui/combobox';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MultiSelect } from '@/components/ui/multi-select';
import type { Option } from '@/components/ui/multi-select';

interface Department {
    id: number;
    name: string;
    code: string;
}

interface LabeledOption {
    value: string;
    label: string;
}

interface UserOption {
    id: number;
    name: string;
    email?: string;
}

interface RequestDetailsCardProps {
    departments: Department[];
    departmentIds: number[];
    onDepartmentIdsChange: (ids: number[]) => void;
    deliveryDate: string;
    onDeliveryDateChange: (date: string) => void;
    title: string;
    onTitleChange: (value: string) => void;
    description: string;
    onDescriptionChange: (value: string) => void;
    priceType: string;
    onPriceTypeChange: (value: string) => void;
    priceTypes: string[];
    purposeType: string;
    onPurposeTypeChange: (value: string) => void;
    purposeTypes: LabeledOption[];
    expectedUsefulLife: string;
    onExpectedUsefulLifeChange: (value: string) => void;
    toBeOrderedById: number | null;
    onToBeOrderedByIdChange: (value: number | null) => void;
    users: UserOption[];
    errors: Record<string, string>;
    isReadOnly?: boolean;
}

export function RequestDetailsCard({
    departments,
    departmentIds,
    onDepartmentIdsChange,
    deliveryDate,
    onDeliveryDateChange,
    title,
    onTitleChange,
    description,
    onDescriptionChange,
    priceType,
    onPriceTypeChange,
    priceTypes,
    purposeType,
    onPurposeTypeChange,
    purposeTypes,
    expectedUsefulLife,
    onExpectedUsefulLifeChange,
    toBeOrderedById,
    onToBeOrderedByIdChange,
    users,
    errors,
    isReadOnly = false,
}: RequestDetailsCardProps) {
    const departmentOptions: Option[] = departments.map((dept) => ({
        label: dept.name,
        value: String(dept.id),
    }));

    const selectedDepartmentValues = departmentIds.map(String);

    const handleDepartmentChange = (values: string[]) => {
        onDepartmentIdsChange(values.map(Number));
    };

    const priceTypeOptions: ComboboxOption[] = priceTypes.map((pt) => ({
        label: pt,
        value: pt,
    }));

    const purposeTypeOptions: ComboboxOption[] = purposeTypes.map((pt) => ({
        label: pt.label,
        value: pt.value,
    }));

    const userOptions: ComboboxOption[] = users.map((user) => ({
        label: user.name,
        value: String(user.id),
    }));

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-lg">Request Details</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-6 md:grid-cols-2">
                <div className="grid gap-2">
                    <Label>Requesting Department/s</Label>
                    <MultiSelect
                        options={departmentOptions}
                        selected={selectedDepartmentValues}
                        onChange={handleDepartmentChange}
                        placeholder="Select departments"
                        disabled={isReadOnly}
                    />
                    {errors.department_ids && (
                        <p className="text-sm text-destructive">
                            {errors.department_ids}
                        </p>
                    )}
                </div>
                <div className="grid gap-2">
                    <Label>Required Delivery Date</Label>
                    <DatePicker
                        date={deliveryDate}
                        onChange={onDeliveryDateChange}
                        disabled={isReadOnly}
                    />
                    {errors.delivery_date && (
                        <p className="text-sm text-destructive">
                            {errors.delivery_date}
                        </p>
                    )}
                </div>
                <div className="grid gap-2">
                    <Label>Price Type</Label>
                    <Combobox
                        options={priceTypeOptions}
                        value={priceType}
                        onChange={onPriceTypeChange}
                        placeholder="Select price type"
                        disabled={isReadOnly}
                    />
                    {errors.price_type && (
                        <p className="text-sm text-destructive">
                            {errors.price_type}
                        </p>
                    )}
                </div>
                <div className="grid gap-2">
                    <Label>Purpose Type</Label>
                    <Combobox
                        options={purposeTypeOptions}
                        value={purposeType}
                        onChange={onPurposeTypeChange}
                        placeholder="Select purpose type"
                        disabled={isReadOnly}
                    />
                    {errors.purpose_type && (
                        <p className="text-sm text-destructive">
                            {errors.purpose_type}
                        </p>
                    )}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="expected_useful_life">
                        Expected Useful Life
                    </Label>
                    <Input
                        id="expected_useful_life"
                        name="expected_useful_life"
                        value={expectedUsefulLife}
                        onChange={(e) =>
                            onExpectedUsefulLifeChange(e.target.value)
                        }
                        placeholder="e.g. 5 years"
                        className="h-10"
                        disabled={isReadOnly}
                    />
                    {errors.expected_useful_life && (
                        <p className="text-sm text-destructive">
                            {errors.expected_useful_life}
                        </p>
                    )}
                </div>
                <div className="grid gap-2">
                    <Label>To Be Ordered By</Label>
                    <Combobox
                        options={userOptions}
                        value={
                            toBeOrderedById === null
                                ? ''
                                : String(toBeOrderedById)
                        }
                        onChange={(value) =>
                            onToBeOrderedByIdChange(
                                value ? Number(value) : null,
                            )
                        }
                        placeholder="Default (config / creator)"
                        disabled={isReadOnly}
                    />
                    {errors.to_be_ordered_by_id && (
                        <p className="text-sm text-destructive">
                            {errors.to_be_ordered_by_id}
                        </p>
                    )}
                </div>
                <div className="grid gap-6 md:col-span-2">
                    {/* Title */}
                    <div className="space-y-1.5">
                        <Label htmlFor="title" className="text-sm font-medium">
                            Title
                        </Label>
                        <Input
                            id="title"
                            name="title"
                            value={title}
                            onChange={(e) => onTitleChange(e.target.value)}
                            placeholder="Office chairs for new hires"
                            className="h-10"
                            disabled={isReadOnly}
                        />
                        {errors.title && (
                            <p className="text-sm text-destructive">
                                {errors.title}
                            </p>
                        )}
                    </div>

                    {/* Purpose */}
                    <div className="space-y-1.5">
                        <Label
                            htmlFor="purpose"
                            className="text-sm font-medium"
                        >
                            Purpose / Justification
                        </Label>
                        <textarea
                            id="purpose"
                            name="description"
                            value={description}
                            onChange={(e) =>
                                onDescriptionChange(e.target.value)
                            }
                            rows={4}
                            placeholder="Explain why this requisition is needed..."
                            className="w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-sm transition focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none disabled:cursor-not-allowed disabled:bg-muted/30 disabled:text-foreground disabled:opacity-100"
                            disabled={isReadOnly}
                        />
                        {errors.description && (
                            <p className="text-sm text-destructive">
                                {errors.description}
                            </p>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
