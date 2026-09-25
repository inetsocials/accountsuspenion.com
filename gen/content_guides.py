"""Blog guides under /blog/{slug}/.

Each guide opens with a direct answer (for answer engines), then sections,
optional HowTo steps, FAQs and official sources. Body entries are either
a paragraph string or a list (rendered as bullets).
"""
from __future__ import annotations

GUIDES = [
    {
        "slug": "how-to-write-an-amazon-plan-of-action",
        "title": "How to write an Amazon Plan of Action that gets read",
        "meta_title": "How to Write an Amazon Plan of Action (Root Cause First)",
        "meta_desc": "How to write an Amazon Plan of Action: identify the real root cause, show completed corrective actions and preventive measures, and attach verifiable evidence.",
        "cat": "commerce", "platforms": ["amazon", "walmart"],
        "answer": "An Amazon Plan of Action has three parts: the root cause of the problem in your own operation, the corrective actions you have already completed, and the preventive measures that stop it happening again. It should be specific, short, factual and supported by documents Amazon can verify. Generic apologies and promises are the most common reason plans are rejected.",
        "sections": [
            ("Start with the notice, not a template", [
                "Read the deactivation notice and every policy page it links to. Note exactly what Amazon asked for: a Plan of Action, invoices, a letter of authorization, or all three. Your plan answers that request and nothing else.",
                "Templates are easy to recognize. Reviewers see thousands of plans, and phrases copied from the internet signal that you have not looked at your own business.",
            ]),
            ("Find the real root cause", [
                "The root cause is the specific failure in your process, not the symptom. Late shipments are a symptom. A carrier cut-off time that your warehouse routinely missed on Fridays is a root cause.",
                ["Pull the order data for the period in question",
                 "Look for patterns by product, supplier, day, carrier or staff member",
                 "Write the cause in one or two plain sentences",
                 "If there is more than one cause, list them separately"],
            ]),
            ("Corrective actions: what you have already done", [
                "Corrective actions fix the orders and customers already affected. Write them in the past tense because they must be complete. Refunds issued, listings corrected, inventory removed, suppliers changed.",
            ]),
            ("Preventive measures: why it will not recur", [
                "Preventive measures change the system. New checks, new tools, new responsibilities, new supplier standards. Name who does what and how often.",
            ]),
            ("Evidence that supports every claim", [
                "Every statement in the plan should be backed by something Amazon can check: invoices with matching names and addresses, updated procedures, screenshots of settings, training records.",
            ]),
        ],
        "steps": [
            ("Read the notice", "List every request in the deactivation notice and linked policies."),
            ("Diagnose the root cause", "Use order data and records to identify the process failure."),
            ("Complete corrective actions", "Fix affected orders, listings and inventory before you write."),
            ("Implement preventive measures", "Change the process and document who owns each step."),
            ("Assemble evidence", "Collect invoices and records that support each statement."),
            ("Submit through Account Health", "Use the Reactivate your account option and keep replies in the same thread."),
        ],
        "faqs": [
            ("How long should a Plan of Action be?", "Long enough to be specific and no longer. Most strong plans fit on a page or two, with evidence attached."),
            ("What if my appeal was already rejected?", "Read the reply carefully and identify what was missing. Resubmitting the same plan with small edits rarely works."),
        ],
        "sources": [("Amazon Seller Central", "https://sellercentral.amazon.com/")],
    },
    {
        "slug": "amazon-funds-held-after-deactivation",
        "title": "Amazon deactivated my account and is holding my funds: what now?",
        "meta_title": "Amazon Funds Held After Deactivation: What Happens Next",
        "meta_desc": "Amazon holding your funds after a deactivation? How held balances work, why the appeal and the disbursement request are separate, and what to prepare.",
        "cat": "commerce", "platforms": ["amazon"],
        "answer": "When Amazon deactivates a seller account, it usually holds the balance while the appeal is open. If the account is reinstated, disbursements resume. If it is not, the remaining balance is handled under the Amazon Services Business Solutions Agreement, subject to refunds, chargebacks and any claims. Plan the appeal and the disbursement request together, because they depend on the same evidence.",
        "sections": [
            ("Two goals, one evidence base", [
                "Reinstatement and fund release are different outcomes. Some accounts will not come back, but the funds still have a route. Fulfillment proof, supplier invoices and resolved customer claims support both.",
            ]),
            ("What to prepare now", [
                ["A reconciliation of the held balance by settlement period",
                 "Proof of delivery for recent orders",
                 "Status of open A-to-z claims and chargebacks",
                 "Supplier invoices for any items under authenticity review"],
            ]),
            ("What not to do", [
                "Do not open a new account to keep selling. Related-account findings can end the original case and complicate the fund release.",
            ]),
        ],
        "faqs": [
            ("Can Amazon keep my money permanently?", "The Business Solutions Agreement sets out when Amazon may withhold amounts, for example to cover claims or in cases of fraud or policy violations. Where the balance is significant and the decision appears wrong, take legal advice on your options."),
        ],
        "sources": [("Amazon Seller Central", "https://sellercentral.amazon.com/")],
    },
    {
        "slug": "paypal-permanent-limitation-180-day-hold",
        "title": "PayPal permanent limitation and the 180-day hold explained",
        "meta_title": "PayPal Permanent Limitation & 180-Day Hold Explained",
        "meta_desc": "PayPal permanently limited your account? What the 180-day hold means, when funds are released, and how to prepare so the release is not delayed.",
        "cat": "payments", "platforms": ["paypal"],
        "answer": "A permanent limitation means PayPal has decided to end the relationship. Under the PayPal User Agreement, PayPal may hold your balance, commonly for up to 180 days, to cover disputes, chargebacks and reversals. After the hold, the remaining balance is usually released, provided there are no outstanding liabilities and any requested information has been supplied.",
        "sections": [
            ("Limited versus permanently limited", [
                "A standard limitation asks you to provide information and can often be lifted. A permanent limitation is a decision to close the account. The wording in the notice and Resolution Center tells you which you have.",
            ]),
            ("How to avoid delays in the release", [
                ["Answer every open request in the Resolution Center",
                 "Resolve disputes and claims promptly",
                 "Keep proof of delivery for all recent sales",
                 "Keep a bank account in your name linked for withdrawal"],
            ]),
            ("Can the decision be reversed?", [
                "Rarely. Reversals happen mostly where the decision rested on a clear factual error, such as a mistaken identity match. We assess this case by case and say plainly when the realistic goal is the release.",
            ]),
        ],
        "faqs": [
            ("Does the 180 days start from the limitation date?", "The notice and your account show the relevant date. Keep a written record of it and any messages from PayPal."),
        ],
        "sources": [("PayPal User Agreement", "https://www.paypal.com/us/legalhub/useragreement-full")],
    },
    {
        "slug": "stripe-account-closed-funds-held",
        "title": "Stripe closed my account: how to get held funds released",
        "meta_title": "Stripe Account Closed & Funds Held: Release Guide",
        "meta_desc": "Stripe closed your account and is holding funds? Why it happens, how reserves work, and how to prepare a review request or an orderly release.",
        "cat": "payments", "platforms": ["stripe"],
        "answer": "When Stripe closes an account, it may pay out remaining funds on a schedule or hold them for a period to cover refunds and disputes, as set out in the Stripe Services Agreement and your notice. If the closure followed information Stripe could not verify, a review with the right documents can sometimes reverse it. If your business type is restricted, focus on an orderly release.",
        "sections": [
            ("Work out why", [
                ["Business on Stripe's restricted businesses list",
                 "High dispute or refund rates",
                 "Information that could not be verified",
                 "Sudden change in volume or ticket size"],
            ]),
            ("What a strong review request contains", [
                "A plain description of what you sell and to whom, a website that matches that description, clear refund and delivery terms, and evidence of fulfillment. Keep it factual.",
            ]),
        ],
        "faqs": [
            ("Can I move to another processor?", "Yes, but disclose your history honestly. If the underlying risk is not fixed, other processors will find the same issue."),
        ],
        "sources": [
            ("Stripe Services Agreement", "https://stripe.com/legal/ssa"),
            ("Stripe restricted businesses", "https://stripe.com/legal/restricted-businesses"),
        ],
    },
    {
        "slug": "youtube-channel-terminated-appeal",
        "title": "YouTube channel terminated: how the appeal really works",
        "meta_title": "YouTube Channel Terminated? How to Appeal Properly",
        "meta_desc": "YouTube channel terminated for strikes, a severe violation or copyright? How termination works, what the appeal needs, and why the first submission matters.",
        "cat": "content", "platforms": ["youtube"],
        "answer": "YouTube terminates channels after three Community Guidelines strikes within 90 days, for a single case of severe abuse, or after repeated copyright strikes. You can appeal a termination using the form linked from the notice. Appeals are limited, so the first submission should explain the context of the content cited, clearly and specifically.",
        "sections": [
            ("Identify the type of termination", [
                ["Community Guidelines strikes: three within 90 days",
                 "Severe abuse or a single serious violation",
                 "Copyright strikes from takedown requests",
                 "Circumvention through a linked channel"],
            ]),
            ("Context is the heart of the appeal", [
                "YouTube allows some content with educational, documentary, scientific or artistic context. If that applies, explain it specifically for each video, with timestamps.",
            ]),
            ("Copyright strikes are different", [
                "They are resolved by a retraction from the claimant, a counter notification, or expiry. A counter notification is a legal process with real risk.",
            ]),
        ],
        "faqs": [
            ("Can I start a new channel?", "Not while terminated. YouTube's terms prohibit it and it can be treated as circumvention."),
        ],
        "sources": [("Community Guidelines strike basics", "https://support.google.com/youtube/answer/2802032")],
    },
    {
        "slug": "facebook-account-suspended-what-to-do",
        "title": "Facebook account suspended: what to do in the first 24 hours",
        "meta_title": "Facebook Account Suspended? What to Do in the First 24 Hours",
        "meta_desc": "Facebook account suspended? First steps: check for hacking, complete identity checks, use the disagree option and avoid moves that make it permanent.",
        "cat": "social", "platforms": ["facebook", "instagram"],
        "answer": "Log in and follow the on-screen steps first. If the account was hacked, use Meta's hacked account recovery. Complete any identity or video selfie check promptly. Use the option to disagree with the decision within the window stated in the notice, commonly 180 days. Do not create a new account while the original is suspended.",
        "sections": [
            ("Was it hacked?", [
                "Look for login alerts, changed email or phone numbers, and posts or ads you did not create. Compromise followed by abuse is one of the most common causes of suspension, and one of the most recoverable.",
            ]),
            ("Complete the checks", [
                "Many suspensions are resolved at the identity check. Use a clear government ID that matches the profile name.",
            ]),
            ("Disagree with the decision, precisely", [
                "Address the Community Standard cited. Explain the context of the content. Keep it short and factual.",
            ]),
        ],
        "faqs": [
            ("Will a new account help me get my business Pages back?", "No. It can be treated as circumvention and puts the original account and the business assets it manages at further risk."),
        ],
        "sources": [
            ("Meta Community Standards", "https://transparency.meta.com/policies/community-standards/"),
            ("Facebook Help Center", "https://www.facebook.com/help/"),
        ],
    },
    {
        "slug": "google-adsense-invalid-traffic-appeal",
        "title": "Google AdSense disabled for invalid traffic: how to appeal",
        "meta_title": "AdSense Disabled for Invalid Traffic? How to Appeal",
        "meta_desc": "AdSense account disabled for invalid traffic? How to find the traffic source, fix it and write an appeal that shows Google what happened and why it will not recur.",
        "cat": "ads", "platforms": ["google-adsense"],
        "answer": "Find the source of the invalid traffic in your analytics and server logs, remove it, and explain in the appeal what you found, what you changed and how you will monitor it. Appeals that say only that you did nothing wrong rarely succeed.",
        "sections": [
            ("Common sources of invalid traffic", [
                ["Accidental clicks by the publisher or their team",
                 "Low-quality paid traffic or traffic exchanges",
                 "Bots and scrapers",
                 "Ad placement that encourages accidental clicks"],
            ]),
            ("Evidence to gather", [
                "Traffic by source, referrer, country and date around the spikes. Records of any paid campaigns. Changes you made to placements.",
            ]),
        ],
        "faqs": [("Will I get my withheld earnings?", "Earnings tied to invalid traffic can be withheld. Whether any are paid depends on the outcome and Google's findings.")],
        "sources": [("AdSense Program policies", "https://support.google.com/adsense/answer/48182")],
    },
    {
        "slug": "google-merchant-center-misrepresentation-fix",
        "title": "Google Merchant Center misrepresentation: what reviewers check",
        "meta_title": "Merchant Center Misrepresentation Suspension: How to Fix It",
        "meta_desc": "Merchant Center suspended for misrepresentation? The trust signals reviewers check on your site and feed, and why to fix everything before a review.",
        "cat": "ads", "platforms": ["google-merchant-center"],
        "answer": "Misrepresentation suspensions are about trust. Reviewers check whether your website clearly shows who you are, how to contact you, what customers pay, and how shipping and returns work, and whether your feed matches the site. Fix every gap before requesting a review, because repeated unsuccessful requests can trigger a waiting period.",
        "sections": [
            ("Website trust signals", [
                ["Clear business identity and contact routes",
                 "Accurate shipping, returns and refund policies",
                 "Secure, working checkout with clear total price",
                 "No exaggerated claims or unrealistic discounts"],
            ]),
            ("Feed consistency", [
                "Price, availability, condition and product identifiers should match the landing page exactly.",
            ]),
        ],
        "faqs": [("How long after fixing should I request review?", "Once every fix is live and crawlable. Check the pages as a new visitor would see them.")],
        "sources": [("Shopping ads policies", "https://support.google.com/merchants/answer/6149970")],
    },
    {
        "slug": "gig-driver-deactivation-appeal",
        "title": "Uber, Lyft, DoorDash or Instacart deactivation: how to appeal",
        "meta_title": "Driver & Courier Deactivation Appeals: Uber, Lyft, DoorDash",
        "meta_desc": "Deactivated as a driver, courier or shopper? How to find the reason, dispute background check errors, use local deactivation rights and prepare the appeal.",
        "cat": "gig", "platforms": ["uber", "lyft", "doordash", "instacart"],
        "answer": "Get the reason in writing, then match the route to it. For background checks, get a copy of the report and dispute errors with the screening company. For ratings, safety reports or fraud flags, gather trip or delivery records, photos and messages, and submit a factual appeal through the platform's official channel. Check whether your city or state has deactivation protections for app-based workers.",
        "sections": [
            ("Background check deactivations", [
                "Under the Fair Credit Reporting Act you are entitled to see a consumer report used against you and to dispute inaccurate information. A corrected report is the strongest appeal evidence there is.",
            ]),
            ("Fraud flags and safety reports", [
                "Timestamps, delivery photos, GPS records and chat logs build a timeline the reviewer can follow.",
            ]),
            ("Local rights", [
                "Some jurisdictions, including Seattle and Minnesota, have introduced deactivation protections for app-based drivers. Local driver organizations and legal aid groups can advise on how they apply to you.",
            ]),
        ],
        "faqs": [("Can I drive for another platform meanwhile?", "Usually yes, unless the reason for deactivation would also disqualify you there.")],
        "sources": [
            ("FTC: using consumer reports", "https://www.ftc.gov/business-guidance/resources/using-consumer-reports-what-employers-need-know"),
        ],
    },
    {
        "slug": "held-funds-wise-payoneer-upwork-fiverr",
        "title": "Held funds on Wise, Payoneer, Upwork and Fiverr: a practical guide",
        "meta_title": "Held Funds on Wise, Payoneer, Upwork & Fiverr: What to Do",
        "meta_desc": "Balance held on Wise, Payoneer, Upwork or Fiverr? How to classify the hold, preserve records and prepare for the official release route without delay.",
        "cat": "payments", "platforms": ["wise", "payoneer", "upwork", "fiverr"],
        "answer": "Classify the hold first: a verification request, a compliance review, a dispute or an account closure. Each has different release conditions. Answer every request completely and consistently, keep a record of all correspondence, and make sure a bank account in your own name is available for the release.",
        "sections": [
            ("Classify the hold", [
                ["Verification: missing or mismatched documents",
                 "Compliance review: source of funds questions",
                 "Dispute: a client or buyer claim",
                 "Closure: the platform is ending the relationship"],
            ]),
            ("Preserve your records", [
                "Download contracts, invoices, message history and statements while you still have access.",
            ]),
        ],
        "faqs": [("Should I pay someone who says they can release the funds?", "No. Only the platform can release funds. Fee-first recovery offers are a common scam.")],
        "sources": [
            ("Wise Help Centre", "https://wise.com/help/"),
            ("Upwork: appealing an account suspension", "https://support.upwork.com/hc/en-us/articles/17989816008339--Appealing-an-account-suspension"),
        ],
    },
    {
        "slug": "suspension-appeal-mistakes",
        "title": "Seven mistakes that turn a recoverable suspension into a permanent one",
        "meta_title": "7 Suspension Appeal Mistakes That Make Bans Permanent",
        "meta_desc": "The appeal mistakes that sink recoverable cases: new accounts, rushed submissions, generic templates, altered documents and more, and what to do instead.",
        "cat": "general", "platforms": ["amazon", "facebook", "youtube", "paypal"],
        "answer": "The mistakes that most often make a suspension permanent are opening a new account, submitting a rushed or generic appeal, sending documents that do not match the account, describing fixes you have not made, arguing with the reviewer, submitting repeated identical appeals, and paying someone who claims inside access.",
        "sections": [
            ("The seven mistakes", [
                ["Opening a new account: treated as ban evasion on almost every platform",
                 "Rushing the first appeal when appeals are limited",
                 "Using a template the reviewer has seen many times",
                 "Documents with mismatched names, addresses or dates",
                 "Describing corrective actions that have not happened",
                 "Blaming the platform or the customer",
                 "Paying for so-called insider reinstatement"],
            ]),
            ("What to do instead", [
                "Slow down enough to diagnose the cause, fix it, and write one precise submission with genuine evidence.",
            ]),
        ],
        "faqs": [("Is it ever right to wait before appealing?", "Yes, when you need time to complete corrective actions or gather documents, provided you stay within the stated window.")],
        "sources": [],
    },
    {
        "slug": "ebay-account-suspended-what-it-means",
        "title": "eBay account suspended: what the notice means and what to do",
        "meta_title": "eBay Account Suspended? What It Means & What to Do",
        "meta_desc": "eBay account suspended or restricted? How to read the notice, the difference between restrictions and suspensions, payout holds, and how to respond.",
        "cat": "commerce", "platforms": ["ebay"],
        "answer": "An eBay suspension blocks selling and sometimes buying, while a restriction limits what you can do. Read the notice for the policy area, check your seller performance, fix the underlying issue, and respond through the channel eBay names in the notice. Never open another account while suspended.",
        "sections": [
            ("Suspension or restriction?", [
                "Restrictions are usually tied to performance and lift as your metrics improve. Suspensions are account decisions, and some are indefinite.",
            ]),
            ("Payout holds", [
                "eBay can hold payouts for new sellers, open cases or risk signals. Holds are released once the condition is met.",
            ]),
        ],
        "faqs": [("Can a linked account get me suspended?", "Yes. eBay links accounts by various signals. If you are linked to a suspended account in error, explain the relationship with evidence.")],
        "sources": [("eBay policies", "https://www.ebay.com/help/policies")],
    },
]

G_BY_SLUG = {g["slug"]: g for g in GUIDES}
