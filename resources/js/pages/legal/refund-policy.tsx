import { Link } from '@inertiajs/react';
import { Ban } from 'lucide-react';
import { LegalLayout, LegalSection } from '@/components/legal-layout';
import type { LegalPageProps } from '@/components/legal-layout';
import { terms } from '@/routes/legal';

/** T-207 (02-10-2026) — GoldWave has no refund and no return anywhere (`Docs/DOMAIN_LOGIC.md` §0, §16.7). */
export default function RefundPolicy(props: LegalPageProps) {
    return (
        <LegalLayout
            {...props}
            page="refund"
            title="Refund & Cancellation Policy"
            intro="Please read this before you pay. GoldWave does not give refunds and does not accept returns."
        >
            <div className="flex items-start gap-3 rounded-xl bg-rose-50 p-4 text-rose-900 ring-1 ring-rose-200">
                <Ban className="mt-0.5 size-5 shrink-0" />
                <p className="font-medium">
                    All payments are final once GoldWave confirms them. There
                    are no refunds, no returns and no cancellations for a refund
                    — for plans, EMIs, store purchases or payouts.
                </p>
            </div>

            <LegalSection title="1. No refunds">
                <p>No amount is refunded, in full or in part, for:</p>
                <ul>
                    <li>
                        the joining payment of any plan (EMI or one-time Direct
                        plan);
                    </li>
                    <li>
                        any EMI, including a &quot;Pay All Remaining EMIs&quot;
                        payment;
                    </li>
                    <li>jewellery bought at a GoldWave store;</li>
                    <li>a payout that has been paid to you.</li>
                </ul>
                <p>
                    This applies whatever the payment method — cash or GPay /
                    UPI.
                </p>
            </LegalSection>

            <LegalSection title="2. No returns">
                <p>
                    Jewellery delivered against a plan, or bought at a GoldWave
                    store, cannot be returned for a refund.
                </p>
            </LegalSection>

            <LegalSection title="3. No cancellation after confirmation">
                <ul>
                    <li>
                        A membership plan cannot be cancelled for a refund once
                        its payment has been confirmed.
                    </li>
                    <li>
                        A switch to Current Rate cannot be undone by the member.
                        Contact us before paying your next EMI if you need help
                        with it.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="4. Before a payment is confirmed">
                <p>
                    Every cash and GPay / UPI payment is checked by our team
                    before it is accepted. A payment that is rejected during
                    this check is not counted towards your plan. If you made a
                    payment by mistake, or paid twice, contact us with the
                    transaction Ref ID before it is approved.
                </p>
            </LegalSection>

            <LegalSection title="5. Buyback is not a refund">
                <p>
                    GoldWave stores may offer to buy jewellery back. A buyback
                    is a separate purchase by the store, at a percentage of the
                    jewellery&apos;s metal value at the current market rate set
                    by GoldWave — not the price you originally paid. It is not a
                    refund of any plan or purchase.
                </p>
            </LegalSection>

            <LegalSection title="6. More information">
                <p>
                    See our{' '}
                    <Link
                        href={terms()}
                        className="text-fuchsia-700 underline underline-offset-2"
                    >
                        Terms &amp; Conditions
                    </Link>{' '}
                    for the full rules of plans, payments and rewards.
                </p>
            </LegalSection>
        </LegalLayout>
    );
}
