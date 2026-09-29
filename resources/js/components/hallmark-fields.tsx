import { useEffect } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type HallmarkPiece = { huid: string; charge: string };

/**
 * T-171 (28-09-2026) — the bill's hallmark section: a "Hallmarked (HUID)" checkbox and, when ticked, one HUID number
 * and one hallmark charge per piece (quantity 2 → 2 rows), entered by hand. The charges are added before GST
 * (DOMAIN_LOGIC.md §16.2 T-171 note).
 */
export function HallmarkFields({
    quantity,
    hallmarked,
    pieces,
    onHallmarkedChange,
    onPiecesChange,
    errors,
    idPrefix,
}: {
    quantity: number;
    hallmarked: boolean;
    pieces: HallmarkPiece[];
    onHallmarkedChange: (value: boolean) => void;
    onPiecesChange: (pieces: HallmarkPiece[]) => void;
    errors: Record<string, string | undefined>;
    idPrefix: string;
}) {
    const count = Math.max(
        1,
        Math.min(100, Number.isFinite(quantity) ? quantity : 1),
    );

    // Keep exactly one row per piece.
    useEffect(() => {
        if (hallmarked && pieces.length !== count) {
            onPiecesChange(
                Array.from(
                    { length: count },
                    (_, i) => pieces[i] ?? { huid: '', charge: '' },
                ),
            );
        }
    }, [hallmarked, count, pieces, onPiecesChange]);

    const update = (
        index: number,
        field: keyof HallmarkPiece,
        value: string,
    ) => {
        onPiecesChange(
            pieces.map((piece, i) =>
                i === index ? { ...piece, [field]: value } : piece,
            ),
        );
    };

    const total = pieces.reduce(
        (sum, piece) => sum + (Number(piece.charge) || 0),
        0,
    );

    return (
        <div className="flex flex-col gap-3 rounded-md border p-3">
            <div className="flex items-center gap-2">
                <Checkbox
                    id={`${idPrefix}_hallmarked`}
                    checked={hallmarked}
                    onCheckedChange={(checked) =>
                        onHallmarkedChange(checked === true)
                    }
                />
                <Label htmlFor={`${idPrefix}_hallmarked`}>
                    Hallmarked (HUID)
                </Label>
            </div>

            {hallmarked && (
                <div className="flex flex-col gap-2">
                    {pieces.map((piece, i) => (
                        <div
                            key={i}
                            className="grid grid-cols-[auto_1fr_8rem] items-start gap-2"
                        >
                            <span className="text-muted-foreground pt-2 text-xs">
                                Piece {i + 1}
                            </span>
                            <div className="grid gap-1">
                                <Input
                                    aria-label={`HUID for piece ${i + 1}`}
                                    placeholder="HUID (6–8 characters)"
                                    value={piece.huid}
                                    maxLength={8}
                                    onChange={(e) =>
                                        update(
                                            i,
                                            'huid',
                                            e.target.value.toUpperCase(),
                                        )
                                    }
                                />
                                {errors[`hallmarks.${i}.huid`] && (
                                    <p className="text-destructive text-xs">
                                        {errors[`hallmarks.${i}.huid`]}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-1">
                                <Input
                                    aria-label={`Hallmark charge for piece ${i + 1}`}
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="Charge ₹"
                                    value={piece.charge}
                                    onChange={(e) =>
                                        update(i, 'charge', e.target.value)
                                    }
                                />
                                {errors[`hallmarks.${i}.charge`] && (
                                    <p className="text-destructive text-xs">
                                        {errors[`hallmarks.${i}.charge`]}
                                    </p>
                                )}
                            </div>
                        </div>
                    ))}
                    <p className="text-muted-foreground text-xs">
                        Hallmark total ₹{total.toFixed(2)} — added to the bill
                        before GST.
                    </p>
                    {errors.hallmarks && (
                        <p className="text-destructive text-xs">
                            {errors.hallmarks}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}
