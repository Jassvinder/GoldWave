1. Pehle poora project samjhao — coding nahi

Saari specification/docs read kare.
Requirements ko understand kare.
Missing/ambiguous requirements identify kare.
Is stage par koi code na likhe.

2. Requirements ko structured project documentation mein convert karvao

Ek clear single source of truth.
Modules/features ka breakdown.
Business rules.
User/admin roles.
Workflows.
Important calculations/formulas.
Acceptance criteria.

3. Saare business rules pehle finalize
   Especially tumhare MLM project mein:

joining rules
sponsor/placement rules
genealogy
commission calculations
eligibility
payout rules
wallet/ledger rules
limits
conditions
edge cases

Aur mathematical rules ke examples ke saath verification karvao.

4. 🔥 Complete Database Schema ek saath design karvao
   Ye wahi point hai jo tumhe yaad tha.

Claude ko bolna tha ki:

Project ke complete requirements ko analyze karke poore system ka database schema design karo before writing migrations/models/application code.

Yaani sirf current module ki tables nahi — poore project ki final database architecture:

all tables
columns
data types
PK/FK
relationships
indexes
unique constraints
enums/statuses
nullable/non-nullable
audit fields
transaction/ledger tables
future extensibility

Aur preferably ERD/relationship documentation bhi.

5. Architecture decide karvao
   Coding se pehle decide:

modules
services
business logic kaha rahegi
compensation/calculation engine ko isolate kaise karna hai
controllers ka role
models
services/actions
jobs/events agar required hain
permissions/security structure

6. Business-rule tests pehle define karvao
   Particularly MLM calculations ke liye.

Example:

Given:
Member A sponsors B
B sponsors C
...
When:
C completes qualifying transaction
Then:
...

Isse Claude implementation karte waqt blindly logic nahi banayega.

7. Phir implementation ko phases mein todna
   Humne roughly ye order discuss kiya tha:

Foundation
↓
Authentication / Roles
↓
Members / Sponsor / Placement / Genealogy
↓
Products / Orders / Payments
↓
Compensation Engine
↓
Wallet / Ledger / Payout
↓
Admin Panel
↓
User Panel
↓
Reports
↓
Security
↓
Testing / Simulation

8. Har phase mein Claude ka loop chalwao

Ye bhi important tha:

READ → PLAN → IMPLEMENT → TEST → VERIFY → FIX → TEST AGAIN → DOCUMENT → COMMIT → NEXT PHASE

Matlab Claude ek feature bana ke “done” bolkar agle feature par na chala jaye.

Usko:

requirements read karni hain
plan banana hai
implement karna hai
tests run karne hain
errors/missing requirements/regressions check karne hain
fix karna hai
dobara test karna hai
documentation update karni hai
commit karna hai
tabhi next phase
Sabse important principle

Tumhare case mein maine basically ye kaha tha:

Claude ko “coder” ki tarah immediately use mat karo. Pehle usse “system architect + analyst” ki tarah kaam karvao.

Yaani:

SPECIFICATION
↓
REQUIREMENT ANALYSIS
↓
BUSINESS RULES
↓
COMPLETE DATABASE DESIGN
↓
SYSTEM ARCHITECTURE
↓
TEST/ACCEPTANCE CRITERIA
↓
IMPLEMENTATION PLAN
↓
CODING
↓
TEST → FIX → RETEST

Aur database schema ko module-by-module banwane ke bajay complete project ko dekhkar ek baar mein design karwana isi wajah se important tha — warna baad mein tables/relationships change hone par migrations, models aur business logic mein unnecessary rework hota hai.

Agar tum usi Claude Code wale workflow ki exact “pre-coding checklist + Claude ko dene wala master prompt” chahte ho, main usko ekdum sequence mein reconstruct karke de sakta hoon — Step 1 se Step 10 tak, aur har step par Claude ko exactly kya prompt dena hai.
