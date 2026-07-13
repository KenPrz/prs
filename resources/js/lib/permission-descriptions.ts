/**
 * Human-readable purpose of each permission, keyed by name. Permissions are
 * defined in database/seeders/data/roles.json — keep this map in sync when
 * adding one there.
 */
const PERMISSION_DESCRIPTIONS: Record<string, string> = {
    'pr.view': 'View purchase requisitions.',
    'pr.prepare': 'Create, edit, and submit purchase requisitions.',
    'pr.approve': 'Act on purchase requisition approval steps assigned to them.',
    'pr.cancel': 'Cancel a purchase requisition.',
    'pr.finalize': 'Upload and bind the final signed PR document.',
    'pr.omit': 'Omit line items from an approved purchase requisition.',
    'po.view': 'View purchase orders.',
    'po.prepare': 'Create, edit, and submit purchase orders.',
    'po.approve': 'Act on purchase order approval steps assigned to them.',
    'po.cancel': 'Cancel a purchase order.',
    'po.finalize': 'Upload and bind the final signed PO document.',
    'po.omit': 'Omit line items from an approved purchase order.',
    'rr.view': 'View receiving reports.',
    'rr.prepare': 'Create, edit, and submit receiving reports.',
    'rr.approve': 'Act on receiving report approval steps assigned to them.',
    'rr.cancel': 'Cancel a receiving report.',
    'prf.view': 'View payment request forms.',
    'prf.prepare': 'Create, edit, and submit payment request forms.',
    'prf.approve': 'Act on payment request form approval steps assigned to them.',
    'prf.cancel': 'Cancel a payment request form.',
    'workflow.reassign': 'Reassign a pending approval step to another user.',
    'config.suppliers.manage': 'Manage the supplier list.',
    'config.departments.manage': 'Manage departments.',
    'config.item_units.manage': 'Manage item units of measure.',
    'config.documents.manage': 'Manage document type settings and templates.',
    'config.workflows.manage': 'Create and edit approval workflow templates.',
    'config.company_profile.manage': 'Edit the company profile.',
    'access.users.manage': 'Create, edit, and delete user accounts.',
    'access.roles.manage': 'Manage roles and their permissions.',
    'access.logs.view': 'View the system access logs.',
};

export function describePermission(name: string): string | undefined {
    return PERMISSION_DESCRIPTIONS[name];
}
