/**
 * T-244 (INSTRUCTIONS.md "Help / Guide") — the in-app Hinglish user guide, section-wise per portal. The user asked for
 * this guide in Hinglish (15-11-2026), so its text is Hinglish on purpose; every other UI copy stays English.
 * Keep it in step with the real behaviour: when a feature changes, change its topic here too.
 */
import type { LucideIcon } from 'lucide-react';
import {
    Award,
    Dices,
    FileText,
    Gem,
    Landmark,
    LayoutGrid,
    LogIn,
    Network,
    Receipt,
    Settings2,
    ShoppingBag,
    Store,
    Trophy,
    UserCheck,
    Users,
    Wallet,
} from 'lucide-react';

export type HelpTopic = {
    id: string;
    title: string;
    /** Short explanation paragraphs. */
    text: string[];
    /** Numbered steps, when the topic is a "how to". */
    steps?: string[];
    /** The sidebar page this topic is about, shown as "Page kholo". */
    page?: string;
    /** Hidden from the company Admin (Super-Admin-only pages). */
    superAdminOnly?: boolean;
};

export type HelpSection = {
    id: string;
    title: string;
    icon: LucideIcon;
    topics: HelpTopic[];
};

export type HelpPortal = 'member' | 'store' | 'staff';

const memberSections: HelpSection[] = [
    {
        id: 'login',
        title: 'Login aur account',
        icon: LogIn,
        topics: [
            {
                id: 'login-how',
                title: 'Login kaise karein',
                text: [
                    'Login sirf tab hota hai jab aapki registration payment approve ho jaaye. Usi waqt aapko Customer ID milti hai (jaise GWL25).',
                    'Do tareeke hain: (1) mobile ya email par OTP mangwa kar, ya (2) User ID = Customer ID aur password se. Shuru mein password bhi aapki Customer ID hi hoti hai.',
                ],
                steps: [
                    'Member Login page kholo.',
                    'OTP ke liye mobile/email daalo aur aaya hua OTP bharo — ya Customer ID aur password daalo.',
                    'Pehli baar login ke baad Settings → Password se apna naya password bana lo (OTP se verify hota hai).',
                ],
            },
            {
                id: 'unpaid-registration',
                title: 'Registration ki payment nahi hui to kya hoga',
                text: [
                    'Payment approve hone tak koi Customer ID nahi milti aur login nahi hota.',
                    'Agar registration ke 1 mahine tak payment nahi aayi, to wo registration apne aap delete ho jaati hai. Agar aapne GPay/UPI se pay karke Ref ID daali hai aur approval baaki hai, to wo delete nahi hoti.',
                ],
            },
        ],
    },
    {
        id: 'dashboard',
        title: 'Dashboard',
        icon: LayoutGrid,
        topics: [
            {
                id: 'dashboard-top',
                title: 'Aaj ka rate aur offers',
                text: [
                    'Dashboard ke sabse upar aaj ka Gold aur Silver rate (₹ / 10 gm) dikhta hai, aur company ke chal rahe Offers, Events aur Notices.',
                    'Neeche My Earnings mein har income alag-alag dikhti hai, aur Wallet Balance mein kitna nikala ya registration mein lagaya.',
                ],
                page: 'Dashboard',
            },
            {
                id: 'dashboard-held',
                title: '"Not in wallet yet" kya hai',
                text: [
                    'Kuch income ban chuki hoti hai par wallet mein abhi nahi aayi: (1) Level Income jo directs poore hone ka wait kar rahi hai, (2) income jo overdue EMI ki wajah se ruki hai. Shart poori hote hi wo apne aap wallet mein aa jaati hai.',
                ],
            },
        ],
    },
    {
        id: 'profile',
        title: 'Profile',
        icon: UserCheck,
        topics: [
            {
                id: 'complete-profile',
                title: 'Complete Profile (PAN, Aadhaar, Bank)',
                text: [
                    'PAN, Aadhaar, photo, address aur bank details ek hi baar bhare jaate hain. Payout ke liye ye zaroori hain aur bank details Super Admin verify karte hain.',
                ],
                steps: [
                    'Sidebar → Complete Profile kholo.',
                    'Saari details aur passbook/cancelled cheque ki photo daalo, Submit karo.',
                    'Bank verify hone tak Dashboard par "awaiting verification" dikhega.',
                ],
                page: 'Complete Profile',
            },
            {
                id: 'change-request',
                title: 'Profile mein badlaav (Change Request)',
                text: [
                    'Ek baar bhari details khud nahi badal sakte. Change Requests page se naya value aur wajah bhejo; Super Admin approve ya reject karenge.',
                    'Email aur mobile kabhi request se nahi badalte — iske liye office aana padta hai.',
                ],
                page: 'Change Requests',
            },
        ],
    },
    {
        id: 'emi',
        title: 'Plan aur EMI',
        icon: Receipt,
        topics: [
            {
                id: 'emi-pay',
                title: 'EMI kaise bharein',
                text: [
                    'EMI Schedule page par saari EMIs, unki due date aur status dikhta hai. Pay button hamesha sabse pehli bachi hui EMI par hota hai — EMIs order mein hi bharti hain.',
                    'Cash ya GPay/UPI se bhari EMI company approve karti hai; tab tak "Awaiting approval" dikhta hai.',
                ],
                steps: [
                    'Sidebar → EMI Schedule kholo.',
                    'Pay dabao, Cash ya GPay/UPI chuno. GPay/UPI mein Ref ID aur screenshot daalo.',
                    'Approve hote hi EMI "paid" ho jaati hai.',
                ],
                page: 'EMI Schedule',
            },
            {
                id: 'emi-advance',
                title: 'EMI advance mein bharna',
                text: [
                    'Jis EMI ki date abhi nahi aayi, use bhi pehle bhar sakte ho — uske aage "Pay in advance" button aata hai. Wo bharte hi agli EMI par button aa jaata hai, is tarah ek-ek karke saari EMIs pehle bhar sakte ho.',
                    'Due dates nahi badalti. Saari bachi EMIs ek saath bharne ke liye "Pay All Remaining EMIs" bhi hai.',
                ],
                page: 'EMI Schedule',
            },
            {
                id: 'emi-office-cash',
                title: 'Office mein cash diya to',
                text: [
                    'Agar aapne EMI ka cash office mein diya hai, to Super Admin ya company Admin use aapke account mein "paid" kar dete hain. Aapko kuch karne ki zaroorat nahi.',
                ],
            },
            {
                id: 'emi-overdue',
                title: 'EMI overdue ho gayi to',
                text: [
                    'Due date ke agle din se EMI overdue ho jaati hai. Jab tak overdue EMI nahi bharte, aapki nayi income wallet mein ruki rehti hai. EMI bharte hi sab ruki hui income aa jaati hai.',
                ],
            },
            {
                id: 'current-rate',
                title: 'Current Rate par booking',
                text: [
                    'Membership Plan page se "Request Booking at Current Rate" bhej sakte ho. Super Admin approve karte hain; approve wale din ka rate aur EMIs fix ho jaate hain.',
                ],
                page: 'Membership Plan',
            },
        ],
    },
    {
        id: 'network',
        title: 'Team aur network',
        icon: Network,
        topics: [
            {
                id: 'directs-tree',
                title: 'Directs View aur Tree View',
                text: [
                    'Directs View: jo log aapne khud join karwaye (sponsor). Tree View: binary tree mein Left/Right placement. Dono alag cheezein hain.',
                ],
                page: 'Directs View',
            },
            {
                id: 'register-new',
                title: 'Naya member join karwana',
                text: [
                    'Register a New Member se kisi naye vyakti ko join karwa sakte ho. Sponsor code, side (Left/Right) aur plan chuno. Payment Cash, GPay/UPI ya apne wallet se ho sakti hai.',
                    'Isi page par aapka apna join link bhi hai — use share karo, naya vyakti khud register kar sakta hai.',
                ],
                page: 'Register a New Member',
            },
        ],
    },
    {
        id: 'income',
        title: 'Income',
        icon: Trophy,
        topics: [
            {
                id: 'level-income',
                title: 'Level Income',
                text: [
                    'Aapki team ke har payment (registration aur har EMI) par 12 levels tak % income milti hai.',
                    'Har level ke liye utne direct members chahiye jo active hon aur jinki registration payment ho chuki ho (jaise L1 = 2, L2 = 4 …). Kam directs par income "On hold" rehti hai aur directs poore hote hi wallet mein aa jaati hai.',
                ],
                page: 'Level Income',
            },
            {
                id: 'pair-reward',
                title: 'Pair/Reward',
                text: [
                    'Har eligible joining Left ya Right mein pair entry banati hai. Milestone ke Left/Right entries aur qualified directs poore hone par mahine ke aakhri din reward milta hai.',
                    'Pair ke liye EMI plan wala direct tab qualified hota hai jab uski zaroori EMIs poori ho jaayein (Pair/Reward page par dikhta hai).',
                ],
                page: 'Pair/Reward',
            },
            {
                id: 'booster',
                title: 'Income Booster',
                text: [
                    'Directs aur team size ki shart poori hone par har mahine fixed booster income milti hai (Level 1, 2, 3). Income Booster page par shart aur schedule dikhta hai.',
                ],
                page: 'Income Booster',
            },
            {
                id: 'draw',
                title: 'Monthly Draw',
                text: [
                    'Har mahine 15 tarikh ko group-wise draw hota hai. Inaam jewellery hai, wallet mein paisa nahi. Agar jeetne wale ke sponsor ke 10 ya zyada directs hain, to sponsor ko bhi wahi inaam milta hai.',
                ],
                page: 'Monthly Draw',
            },
        ],
    },
    {
        id: 'wallet',
        title: 'Wallet aur Payout',
        icon: Wallet,
        topics: [
            {
                id: 'wallet-ledger',
                title: 'Wallet',
                text: [
                    'Har credit aur debit ki entry wajah ke saath Wallet page par hoti hai, jaise "Level 1 income from GWL45\'s payment".',
                ],
                page: 'Wallet',
            },
            {
                id: 'payout',
                title: 'Payout (paisa nikalna)',
                text: [
                    'Payout request bhejo; Super Admin mahine mein process karte hain. Minimum amount Super Admin set karte hain. Bank details verified honi chahiye.',
                    'Request ka amount process hone tak wallet mein "On hold" rehta hai.',
                ],
                steps: [
                    'Sidebar → Payout kholo.',
                    'Amount daalo aur request bhejo.',
                    'Status Payout page par dekho.',
                ],
                page: 'Payout',
            },
        ],
    },
    {
        id: 'other',
        title: 'Reports aur Notifications',
        icon: FileText,
        topics: [
            {
                id: 'member-reports',
                title: 'Reports',
                text: [
                    'Reports page se apni income, payments aur wallet ki report download kar sakte ho.',
                ],
                page: 'Reports',
            },
            {
                id: 'member-notifications',
                title: 'Notifications',
                text: [
                    'Upar ki ghanti (bell) mein naye alerts dikhte hain: payment approve/reject, EMI reminder, payout status.',
                ],
                page: 'Notifications',
            },
        ],
    },
];

const storeSections: HelpSection[] = [
    {
        id: 'store-basics',
        title: 'Store portal',
        icon: Store,
        topics: [
            {
                id: 'store-login',
                title: 'Login aur Store Dashboard',
                text: [
                    'Store Admin, Store login page se apne login details se login karta hai. Store Dashboard par store ki sales, Store Wallet balance aur stock ka saar dikhta hai.',
                ],
                page: 'Store Dashboard',
            },
            {
                id: 'store-wallet',
                title: 'Store Wallet',
                text: [
                    'Store Wallet store ka payment float hai jo company deti hai. Store se cash collection aur wallet-funded registration isi se katte hain. Top-up company karti hai.',
                ],
                page: 'Store Transactions',
            },
        ],
    },
    {
        id: 'store-sales',
        title: 'Sales aur payments',
        icon: ShoppingBag,
        topics: [
            {
                id: 'store-sale',
                title: 'Repurchase / Sale record karna',
                text: [
                    'Member ya walk-in customer ko jewellery bechne par Repurchases / Sales page se sale record karo. Member ki sale par upline ko Purchase/Repurchase income aur store profit distribution banta hai.',
                ],
                steps: [
                    'Repurchases / Sales kholo.',
                    'Customer ID (member) daalo ya walk-in chuno, item aur weight bharo.',
                    'Rate aur making apne aap lagte hain; confirm karo aur invoice print karo.',
                ],
                page: 'Repurchases / Sales',
            },
            {
                id: 'store-collect-cash',
                title: 'Member ka pending cash collect karna',
                text: [
                    'Member ne Cash chuna hai aur store par paisa diya, to Sales page ke "Collect a Pending Cash Payment" mein Customer ID daalkar Collect karo. Ye Store Wallet se settle hota hai.',
                ],
                page: 'Repurchases / Sales',
            },
            {
                id: 'store-emi',
                title: 'Repurchase on EMI',
                text: [
                    'Member EMI par jewellery le sakta hai (Current Rate par). Request company approve karti hai; EMI member apne EMI page se bharta hai.',
                ],
                page: 'Repurchase on EMI',
            },
            {
                id: 'store-register',
                title: 'Naya member register karna',
                text: [
                    'Register a New Member se store bhi naya member join karwa sakta hai, payment Store Wallet se bhi ho sakti hai.',
                ],
                page: 'Register a New Member',
            },
        ],
    },
    {
        id: 'store-stock',
        title: 'Stock aur reports',
        icon: Gem,
        topics: [
            {
                id: 'store-inventory',
                title: 'Inventory',
                text: [
                    'Inventory page par store ka stock dikhta hai. Sale par stock apne aap ghatta hai; company restock bhejti hai.',
                ],
                page: 'Inventory',
            },
            {
                id: 'store-reports',
                title: 'Store Reports aur Notifications',
                text: [
                    'Store Reports se sales aur wallet ki report nikalo. Ghanti mein company ke alerts aate hain.',
                ],
                page: 'Store Reports',
            },
        ],
    },
];

const staffSections: HelpSection[] = [
    {
        id: 'staff-daily',
        title: 'Roz ka kaam',
        icon: LayoutGrid,
        topics: [
            {
                id: 'approve-payments',
                title: 'Cash / GPay-UPI payment approve karna',
                text: [
                    'Members ki Cash aur GPay/UPI payments Payment Approvals page par aati hain. Approve karte hi member active hota hai (registration par Customer ID banti hai) aur saari income apne aap banti hai.',
                    'GPay/UPI mein Ref ID aur screenshot check karo. Galat ho to Reject karo — member dobara pay kar sakta hai.',
                ],
                page: 'Payment Approvals',
            },
            {
                id: 'record-cash-emi',
                title: 'Office mein mila cash EMI record karna',
                text: [
                    'Koi member account nahi kholta aur EMI ka cash office mein deta hai: Member Management → member kholo → EMI card par "Record Cash EMI". Ye member ki sabse pehli bachi EMI (advance bhi) turant paid kar deta hai. Agli EMI ke liye dobara dabao.',
                    'Agar member ne pehle se payment submit ki hai, to wahi approve hoti hai — duplicate nahi banta.',
                ],
                page: 'Member Management',
            },
            {
                id: 'payouts',
                title: 'Payout Requests',
                text: [
                    'Members ki payout requests yahan aati hain. Bank details verified honi chahiye. Payment karke mode aur reference (UTR/cheque) bharo aur Processed karo; fail ho to Failed — member ka balance wapas aa jaata hai.',
                ],
                page: 'Payout Requests',
            },
            {
                id: 'requests',
                title: 'Baaki requests',
                text: [
                    'Rate Booking Requests (Current Rate), Repurchase EMI Requests aur Profile Change Requests — sab Requests section mein. Cancel/Reject par wajah likhna zaroori hai.',
                ],
                page: 'Profile Change Requests',
            },
        ],
    },
    {
        id: 'staff-members',
        title: 'Members',
        icon: Users,
        topics: [
            {
                id: 'member-management',
                title: 'Member Management',
                text: [
                    'Customer ID, naam ya mobile se member dhoondo. Member Detail par profile, plan, EMI, income history, network aur bank verify ka button hai. Email/mobile yahin se badalte hain.',
                ],
                page: 'Member Management',
            },
            {
                id: 'unpaid-purge',
                title: 'Bina payment wali registrations',
                text: [
                    'Jis registration ki 1 mahine tak payment nahi aayi, wo raat 1 baje apne aap delete ho jaati hai. GPay/UPI Ref ID wali (approval baaki) aur jiske neeche koi placed hai, wo delete nahi hoti.',
                ],
            },
            {
                id: 'restrictions',
                title: 'Payout Hold aur Account Block',
                text: [
                    'Member Restrictions se payout rokna ya account block karna. Har badlaav par password dobara maanga jaata hai.',
                ],
                page: 'Member Restrictions',
                superAdminOnly: true,
            },
            {
                id: 'dummy',
                title: 'Dummy entries',
                text: [
                    'Dummy Entry Settings mein roz ki dummy entries ki ginti; Dummy Entry Assignment se kisi leader ko dummy entry di jaati hai.',
                ],
                page: 'Dummy Entry Settings',
                superAdminOnly: true,
            },
        ],
    },
    {
        id: 'staff-compensation',
        title: 'Income aur rules',
        icon: Award,
        topics: [
            {
                id: 'rule-versions',
                title: 'Rule Versions (income %, directs, milestones)',
                text: [
                    'Level %, har level ke directs, Pair milestones, Booster levels, Pair qualification EMIs — sab yahin set hote hain. Naya version publish karne par purani income nahi badalti. Password dobara maanga jaata hai.',
                    'Code badalne se pehle dekho — zyada tar rules yahin se badal jaate hain.',
                ],
                page: 'Rule Versions',
                superAdminOnly: true,
            },
            {
                id: 'membership-plans',
                title: 'Membership Plans',
                text: [
                    'Plans ka naam, amount, EMIs, weight aur on/off. Purane members par plan edit ka asar nahi padta.',
                ],
                page: 'Membership Plans',
                superAdminOnly: true,
            },
            {
                id: 'verify',
                title: 'Earnings Verification aur Audit',
                text: [
                    'Earnings Verification har income dobara calculate karke milata hai — hara (green) matlab sab sahi. Compensation Audit mein har calculation aur skip/hold ki wajah dikhti hai. Upline Earnings: ek member ki wajah se upar kise kya mila.',
                ],
                page: 'Earnings Verification',
            },
        ],
    },
    {
        id: 'staff-draw',
        title: 'Draw',
        icon: Dices,
        topics: [
            {
                id: 'draw-manage',
                title: 'Draw Settings aur Draw Management',
                text: [
                    'Group size aur mahine ka inaam Draw Settings mein. 15 tarikh ko groups bante hain aur draw hota hai; office mein draw hua to Draw Management mein jeetne walon ki Customer IDs bharo.',
                ],
                page: 'Draw Management',
            },
        ],
    },
    {
        id: 'staff-settings',
        title: 'Settings',
        icon: Settings2,
        topics: [
            {
                id: 'rates',
                title: 'Gold & Silver Rates',
                text: [
                    'Roz ka rate (per 10 gm) aur making % date ke saath daalo. Wahi rate member dashboard par "Today\'s Rates" mein dikhta hai aur sales/EMI booking mein lagta hai.',
                ],
                page: 'Gold & Silver Rates',
            },
            {
                id: 'notices',
                title: 'Dashboard Notices (offer, event, notice)',
                text: [
                    'Member dashboard ke upar dikhne wale Offers, Events aur Notices yahan se. Dates optional hain; "Show this notice" hata kar bina delete kiye chhupa sakte ho.',
                ],
                steps: [
                    'Settings → Dashboard Notices kholo.',
                    'Add Notice dabao, Type, Title, Details aur dates bharo.',
                    'Save karte hi members ko dikhne lagta hai (agar date range mein hai).',
                ],
                page: 'Dashboard Notices',
            },
            {
                id: 'payout-settings',
                title: 'Payout & TDS, Payment Settings (UPI)',
                text: [
                    'Minimum payout aur TDS % Payout & TDS Settings mein. Company ka UPI ID aur QR Payment Settings mein — har payment page par yahi dikhta hai.',
                ],
                page: 'Payout & TDS Settings',
            },
            {
                id: 'landing',
                title: 'Landing Page',
                text: [
                    'Public website ki heading, text aur contact details yahan se badlo.',
                ],
                page: 'Landing Page',
            },
        ],
    },
    {
        id: 'staff-stores',
        title: 'Stores aur company wallet',
        icon: Landmark,
        topics: [
            {
                id: 'stores',
                title: 'Store Management aur Store Wallets',
                text: [
                    'Naya store banao, owner assign karo, jewellery allocation aur advance daalo. Store Wallets mein top-up aur ledger. Restock Shipments mein store ko bhejne wala stock.',
                ],
                page: 'Store Management',
            },
            {
                id: 'company-wallet',
                title: 'Company Wallet',
                text: [
                    'Isme paisa tab aata hai jab koi member ya store apne wallet se kisi naye member ki registration bharta hai, ya Super Admin top-up kare. Cash registrations isme nahi aati.',
                ],
                page: 'Company Wallet',
            },
            {
                id: 'reports',
                title: 'Reports aur Financial Summary',
                text: [
                    'Reports se export (Excel/PDF) nikalo. Financial Summary (sirf Super Admin) mein company ka paisa aaya-gaya ek jagah.',
                ],
                page: 'Reports',
            },
        ],
    },
];

export const HELP: Record<
    HelpPortal,
    { title: string; intro: string; sections: HelpSection[] }
> = {
    member: {
        title: 'Member Guide',
        intro: 'GoldWave member portal mein kya kahan hai aur kaise kaam karta hai — section ke hisaab se. Upar search mein koi bhi shabd likho (jaise "EMI", "payout").',
        sections: memberSections,
    },
    store: {
        title: 'Store Guide',
        intro: 'Store portal ke saare kaam ek jagah — sale, cash collection, stock aur reports.',
        sections: storeSections,
    },
    staff: {
        title: 'Admin Guide',
        intro: 'Super Admin aur company Admin ke liye — roz ka kaam, members, income rules, draw aur settings.',
        sections: staffSections,
    },
};
