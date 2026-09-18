import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    iconBadgeColorClasses,
    type StatCardColor,
} from '@/components/stat-card';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

export type FormSectionColor = StatCardColor;

export type FormSectionProps = {
    icon: LucideIcon;
    color: FormSectionColor;
    title: string;
    description?: string;
    /** Rendered at the header's trailing edge (e.g. a status badge or info tag). */
    action?: ReactNode;
    children: ReactNode;
    className?: string;
    contentClassName?: string;
};

/**
 * Settings-card pattern (T-103) — icon-badge header + description, matching
 * `Docs/Screenshots/5.png`'s Compensation Settings layout. Wraps every
 * settings/profile/change-request form section across all 3 portals; the
 * label-above-input grid and repeatable-rows sub-table inside it stay
 * page-specific since their fields differ per form.
 */
export function FormSection({
    icon: Icon,
    color,
    title,
    description,
    action,
    children,
    className,
    contentClassName,
}: FormSectionProps) {
    return (
        <Card className={className}>
            <CardHeader className="flex-row items-start justify-between gap-4 space-y-0">
                <div className="flex items-start gap-3">
                    <div
                        className={cn(
                            'flex size-10 shrink-0 items-center justify-center rounded-lg',
                            iconBadgeColorClasses[color],
                        )}
                    >
                        <Icon className="size-5" />
                    </div>
                    <div>
                        <CardTitle>{title}</CardTitle>
                        {description && (
                            <CardDescription>{description}</CardDescription>
                        )}
                    </div>
                </div>
                {action}
            </CardHeader>
            <CardContent className={contentClassName}>{children}</CardContent>
        </Card>
    );
}
