import { Link } from '@inertiajs/react';
import { LegalLayout, LegalSection } from '@/components/legal-layout';
import type { LegalPageProps } from '@/components/legal-layout';
import { privacy, refund } from '@/routes/legal';

const linkClass = 'text-fuchsia-700 underline underline-offset-2';

/** T-207 (02-10-2026) — draft Terms & Conditions, written from `Docs/DOMAIN_LOGIC.md`. Have it reviewed by a legal adviser. */
export default function Terms(props: LegalPageProps) {
    return (
        <LegalLayout
            {...props}
            page="terms"
            title="Terms & Conditions"
            intro="These terms apply to everyone who registers with, pays to, or uses GoldWave. By registering you agree to them."
        >
            <LegalSection title="1. About GoldWave">
                <p>
                    GoldWave (&quot;GoldWave&quot;, &quot;we&quot;,
                    &quot;us&quot;) runs a gold and silver jewellery membership
                    program. A member chooses a plan, pays for it in instalments
                    or at once, and receives gold or silver jewellery. Members
                    can also earn rewards under the rules described below.
                </p>
            </LegalSection>

            <LegalSection title="2. Your account">
                <ul>
                    <li>
                        You must give true and complete details, and keep them
                        up to date. Locked details (such as PAN, Aadhaar,
                        address and bank details) can be changed only through a
                        change request approved by GoldWave.
                    </li>
                    <li>
                        You log in with your Customer ID and password. Keep your
                        password secret; you are responsible for activity on
                        your account. Change the first password after your first
                        login.
                    </li>
                    <li>
                        PAN, Aadhaar, bank details and a bank proof must be
                        submitted before payouts can be made.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="3. Joining">
                <ul>
                    <li>
                        You join with the sponsor code of the member who
                        introduced you, and choose the Left or Right side for
                        your placement at registration.
                    </li>
                    <li>
                        Your sponsor (who introduced you) and your placement
                        (your position in the Left/Right tree) are separate.
                    </li>
                    <li>
                        Your membership becomes active, and your Customer ID is
                        issued, only after GoldWave confirms your first payment.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="4. Plans and jewellery">
                <ul>
                    <li>
                        Every plan benefit is a jewellery item — not coins, bars
                        or cash.
                    </li>
                    <li>
                        <strong>EMI plans on Future Rate</strong> (the default):
                        at the end you receive jewellery worth the amount you
                        paid, at the metal rate on the delivery date.
                    </li>
                    <li>
                        <strong>Current Rate</strong> (optional, needs
                        GoldWave&apos;s approval): the plan&apos;s fixed weight
                        and that day&apos;s rate are locked, and your remaining
                        EMIs are recalculated, including a monthly maintenance
                        charge. A member cannot undo this switch.
                    </li>
                    <li>
                        <strong>Direct plans</strong> (one-time): you choose
                        jewellery at the rate on the date your payment is
                        confirmed.
                    </li>
                    <li>
                        Hallmarking charges and GST, where applicable, are added
                        on the delivery bill.
                    </li>
                    <li>
                        Jewellery is collected from a GoldWave store once the
                        plan is fully paid.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="5. Payments">
                <ul>
                    <li>
                        You can pay in cash or by GPay / UPI. For GPay / UPI you
                        must give the transaction Ref ID and a screenshot. A Ref
                        ID can be used only once.
                    </li>
                    <li>
                        A payment counts only after GoldWave has checked and
                        confirmed it.
                    </li>
                    <li>
                        Your first EMI is due on your activation date, and each
                        next EMI on the same date of the following months. You
                        pay the earliest unpaid EMI, or all remaining EMIs at
                        once.
                    </li>
                    <li>
                        An EMI not paid by its due date becomes overdue the next
                        day. While any EMI is overdue, all your new earnings are
                        held and are released after you pay.
                    </li>
                    <li>
                        All confirmed payments are final — see the{' '}
                        <Link href={refund()} className={linkClass}>
                            Refund &amp; Cancellation Policy
                        </Link>
                        .
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="6. Rewards">
                <ul>
                    <li>
                        Members may earn Level Income, Pair Rewards, Monthly
                        Draw prizes, Income Booster benefits and store purchase
                        income, under the rules and rates shown in the member
                        portal and plan guide.
                    </li>
                    <li>
                        <strong>No income is guaranteed.</strong> Rewards depend
                        on the plan rules and on real activity in your team.
                        Your plan entitlement is the jewellery.
                    </li>
                    <li>
                        Many rewards need a number of qualified direct members.
                        Income that is not yet payable may be held until the
                        conditions are met, as described in the rules.
                    </li>
                    <li>
                        GoldWave may change reward rules and rates from time to
                        time. A change applies from its effective date and does
                        not alter rewards already calculated.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="7. Monthly Draw">
                <p>
                    Eligible members are placed in draw groups and a winner is
                    picked at random each month. Prizes are jewellery set by
                    GoldWave. The draw result recorded by the system is final.
                </p>
            </LegalSection>

            <LegalSection title="8. Wallet and payouts">
                <ul>
                    <li>
                        Rewards are credited to your GoldWave wallet. You can
                        request a payout once your balance reaches the minimum
                        set by GoldWave; you cannot withdraw directly.
                    </li>
                    <li>
                        Payouts are reviewed and paid by GoldWave, usually
                        monthly, to your verified bank account or UPI. TDS and
                        any processing fee are deducted as required by law and
                        by GoldWave&apos;s settings.
                    </li>
                    <li>A payout, once paid, is final.</li>
                </ul>
            </LegalSection>

            <LegalSection title="9. Fair use">
                <ul>
                    <li>
                        Do not give false details, create accounts for people
                        who have not agreed to join, or misuse another
                        person&apos;s account.
                    </li>
                    <li>
                        Do not promise anyone guaranteed income or describe
                        GoldWave in a misleading way.
                    </li>
                    <li>
                        GoldWave may hold earnings, or suspend or deactivate an
                        account, involved in fraud, misuse or a breach of these
                        terms.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="10. Privacy">
                <p>
                    How we collect and use your information is explained in our{' '}
                    <Link href={privacy()} className={linkClass}>
                        Privacy Policy
                    </Link>
                    .
                </p>
            </LegalSection>

            <LegalSection title="11. Changes to these terms">
                <p>
                    We may update these terms. The &quot;Last updated&quot; date
                    above shows the latest version. Continuing to use GoldWave
                    after a change means you accept the updated terms.
                </p>
            </LegalSection>

            <LegalSection title="12. Governing law">
                <p>
                    These terms are governed by the laws of India. Disputes are
                    subject to the courts having jurisdiction where
                    GoldWave&apos;s registered office is located.
                </p>
            </LegalSection>
        </LegalLayout>
    );
}
