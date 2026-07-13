export type Supplier = {
    id: number;
    name: string;
};

export type ItemUnit = {
    id: number;
    name: string;
    code: string;
};

export type Department = {
    id: number;
    name: string;
    code: string;
};

export type LineItemModel = {
    id: number;
    name: string;
    quantity: number;
    unit_id: number;
    price: string;
    unit?: ItemUnit;
    quantity_allocated?: number;
    quantity_unallocated?: number;
    is_omitted?: boolean;
    omitted_at?: string | null;
    omit_reason?: string | null;
};

export type PurchaseRequisitionRef = {
    id: number;
    pr_number: string;
    title: string;
    status: string;
};

export type PurchaseOrderRow = {
    id: number;
    po_number: string;
    status: string;
    supplier?: Supplier | null;
    purchase_requisition?: PurchaseRequisitionRef | null;
    created_at: string;
};

export type PurchaseOrderItemModel = {
    id: number;
    line_item_id: number;
    quantity: number;
    unit_id: number;
    price: string;
    line_item?: LineItemModel;
    quantity_received?: number;
    quantity_remaining?: number;
    receiving_report_items?: ReceivingReportItemModel[];
    is_omitted?: boolean;
    omitted_at?: string | null;
    omit_reason?: string | null;
};

export type PurchaseOrderModel = {
    id: number;
    po_number: string;
    status: string;
    expected_delivery_date: string | null;
    terms_and_conditions: string | null;
    remarks: string | null;
    bill_to_id?: number | null;
    ship_to_id?: number | null;
    payment_terms?: string | null;
    currency?: string | null;
    price_type: string;
    raw_total?: number;
    net_total?: number;
    gross_total?: number;
    vat_total?: number;
    supplier?: Supplier | null;
    purchase_requisition?: PurchaseRequisitionRef | null;
    bill_to_address?: {
        recipient_name: string;
        street: string;
        city: string;
    } | null;
    ship_to_address?: {
        recipient_name: string;
        street: string;
        city: string;
    } | null;
    items?: PurchaseOrderItemModel[];
    receiving_reports?: ReceivingReportRow[];
    workflow_instance_id?: number | null;
};

export type ReceivingReportRow = {
    id: number;
    rr_number: string;
    received_date: string;
    status: string;
    received_by?: { id: number; name: string } | null;
    purchase_order?: { id: number; po_number: string } | null;
};

export type ReceivingReportItemModel = {
    id: number;
    purchase_order_item_id: number;
    quantity_received: number;
    quantity_rejected: number;
    remarks: string | null;
    purchase_order_item?: PurchaseOrderItemModel;
};

export type ReceivingReportModel = {
    id: number;
    rr_number: string;
    received_date: string;
    status: string;
    remarks: string | null;
    received_by?: { id: number; name: string } | null;
    purchase_order?: PurchaseOrderModel | null;
    items?: ReceivingReportItemModel[];
    workflow_instance_id?: number | null;
};
