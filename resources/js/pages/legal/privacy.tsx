import { LegalLayout, LegalSection } from '@/components/legal-layout';
import type { LegalPageProps } from '@/components/legal-layout';

/** T-207 (02-10-2026) — draft Privacy Policy, written from what the app actually stores. Have it reviewed by a legal adviser. */
export default function Privacy(props: LegalPageProps) {
    return (
        <LegalLayout
            {...props}
            page="privacy"
            title="Privacy Policy"
            intro="This explains what information GoldWave collects, why, and how we keep it safe."
        >
            <LegalSection title="1. Information we collect">
                <ul>
                    <li>
                        <strong>Registration:</strong> name, mobile number,
                        email, gender, sponsor code and chosen placement side.
                    </li>
                    <li>
                        <strong>Profile and KYC:</strong> PAN, Aadhaar, address,
                        profile photo, bank account details and a bank proof
                        (passbook or cancelled cheque).
                    </li>
                    <li>
                        <strong>Payments:</strong> amounts, dates, payment
                        method, GPay / UPI transaction Ref IDs and payment
                        screenshots.
                    </li>
                    <li>
                        <strong>Membership activity:</strong> your plan, EMIs,
                        network (sponsor and placement), rewards, wallet,
                        payouts, draw results and store purchases.
                    </li>
                    <li>
                        <strong>Technical:</strong> login sessions and cookies
                        needed to keep you signed in and to remember which login
                        page you use.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="2. How we use it">
                <ul>
                    <li>
                        To create and run your membership, confirm payments, and
                        deliver your jewellery.
                    </li>
                    <li>
                        To calculate rewards, process payouts and deduct taxes
                        where required.
                    </li>
                    <li>
                        To send you notifications — for example EMI reminders,
                        payment approvals and payout updates — by in-app
                        message, email or SMS.
                    </li>
                    <li>
                        To verify identity and bank details, prevent fraud, and
                        meet legal and tax obligations.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="3. Who can see it">
                <ul>
                    <li>
                        <strong>GoldWave staff</strong> (Super Admin and Admin)
                        see the details they need to run the program. Bank
                        proofs and payment screenshots are stored privately and
                        can be opened only by logged-in GoldWave staff.
                    </li>
                    <li>
                        <strong>Other members:</strong> your name and Customer
                        ID can appear in the network views of members above you
                        (for example your sponsor&apos;s Directs and Tree
                        pages). Your KYC and bank details are never shown to
                        them.
                    </li>
                    <li>
                        <strong>Stores:</strong> a GoldWave store sees the
                        details needed for a sale or a jewellery delivery made
                        through it.
                    </li>
                    <li>
                        <strong>Service providers</strong> that help us run
                        GoldWave (such as hosting, email and SMS), only for that
                        purpose.
                    </li>
                    <li>
                        <strong>Authorities</strong>, when the law requires it.
                    </li>
                </ul>
                <p>We do not sell your personal information.</p>
            </LegalSection>

            <LegalSection title="4. How we protect it">
                <p>
                    Passwords are stored in encrypted (hashed) form. Access to
                    member information is limited by role, and sensitive
                    documents are kept in private storage. No system is
                    perfectly secure, so please keep your password safe and tell
                    us if you notice anything unusual on your account.
                </p>
            </LegalSection>

            <LegalSection title="5. How long we keep it">
                <p>
                    We keep your information for as long as your membership is
                    active and afterwards as long as needed for legal, tax and
                    audit purposes. Financial records (payments, rewards,
                    payouts) are never deleted or overwritten, so that every
                    transaction can be traced.
                </p>
            </LegalSection>

            <LegalSection title="6. Your choices">
                <ul>
                    <li>
                        You can see your details on your Profile page in the
                        member portal.
                    </li>
                    <li>
                        To correct a locked detail, file a change request from
                        your Profile page; GoldWave reviews it.
                    </li>
                    <li>
                        For any other question about your information, contact
                        us using the details below.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="7. Changes to this policy">
                <p>
                    We may update this policy. The &quot;Last updated&quot; date
                    above shows the latest version.
                </p>
            </LegalSection>
        </LegalLayout>
    );
}
