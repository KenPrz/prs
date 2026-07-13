import { OmitItemButton } from './omit-item-button';

type OmittedItem = {
    name: string;
    quantity: number;
    reason?: string | null;
    endpoint: string;
};

export function OmittedItemsTable({ items }: { items: OmittedItem[] }) {
    if (items.length === 0) {
        return null;
    }

    return (
        <div className="mt-6">
            <h3 className="mb-2 text-sm font-medium text-muted-foreground">
                Omitted Items
            </h3>
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-muted-foreground">
                        <th className="pb-3 text-left font-medium">
                            Description
                        </th>
                        <th className="w-[70px] pb-3 text-center font-medium">
                            Qty
                        </th>
                        <th className="pb-3 text-left font-medium">Reason</th>
                        <th className="w-[110px] pb-3" />
                    </tr>
                </thead>
                <tbody>
                    {items.map((it) => (
                        <tr
                            key={it.endpoint}
                            className="border-b align-top opacity-70"
                        >
                            <td className="py-3 pr-2 text-muted-foreground line-through">
                                {it.name}
                            </td>
                            <td className="py-3 text-center">{it.quantity}</td>
                            <td className="py-3 pr-2 text-muted-foreground">
                                {it.reason || '—'}
                            </td>
                            <td className="py-3 text-right">
                                <OmitItemButton
                                    endpoint={it.endpoint}
                                    isOmitted
                                />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
