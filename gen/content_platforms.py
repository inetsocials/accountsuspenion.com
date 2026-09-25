"""Platform reinstatement pages.

`legacy=True` marks a URL already indexed on the live site. Those slugs are
fixed and must never change. New platforms follow the same
/{platform}-reinstatement/ pattern so the URL architecture stays uniform.

Every fact here should be checkable against the platform's own published
policies (see `sources`). Avoid numbers that the platform does not publish.
"""
from __future__ import annotations

CATEGORIES: dict[str, dict] = {
    "social": {
        "name": "Social media",
        "path": "/social-media-reinstatement/",
        "icon": "users",
        "h1": "Social media account reinstatement",
        "meta_title": "Social Media Account Reinstatement | Suspended & Disabled",
        "meta_desc": "Suspended or disabled on Facebook, Instagram, X, TikTok, Reddit, LinkedIn or Discord? Independent case review and appeal preparation built on the notice.",
        "lede": "Personal, creator and business profiles on the major social networks. We work from the enforcement notice, the policy it cites and the appeal route the platform actually offers.",
    },
    "commerce": {
        "name": "E-commerce and marketplaces",
        "path": "/ecommerce-reinstatement/",
        "icon": "store",
        "h1": "Marketplace seller account reinstatement",
        "meta_title": "Seller Account Reinstatement | Amazon, eBay, Walmart & More",
        "meta_desc": "Seller account suspended or deactivated on Amazon, eBay, Walmart, Etsy, Shopify or StockX? Root cause analysis, Plan of Action writing and appeal support.",
        "lede": "Seller accounts on Amazon, eBay, Walmart, Etsy, Shopify and specialist marketplaces. Performance, authenticity, intellectual property, verification and related-account cases.",
    },
    "payments": {
        "name": "Payments and held funds",
        "path": "/payment-account-reinstatement/",
        "icon": "card",
        "h1": "Payment account reinstatement and held funds",
        "meta_title": "Payment Account Reinstatement & Held Funds | PayPal, Stripe",
        "meta_desc": "PayPal limitation, Stripe closure, Wise, Payoneer or Coinbase restriction? We classify the hold, build the evidence and prepare the official release request.",
        "lede": "Limitations, closures, reserves and held balances on payment processors and money platforms. Two separate goals: restore the account where possible, and release the funds either way.",
    },
    "ads": {
        "name": "Advertising and publishing",
        "path": "/advertising-account-reinstatement/",
        "icon": "megaphone",
        "h1": "Advertising and publisher account reinstatement",
        "meta_title": "Ad Account Reinstatement | Google Ads, AdSense & Merchant",
        "meta_desc": "Google Ads suspended, AdSense disabled, Merchant Center misrepresentation or Meta ad account restricted? Policy mapping, fixes and review requests.",
        "lede": "Ad accounts, publisher accounts and product feeds. These cases turn on policy fit: what the reviewer sees on your site, in your feed and in your account history.",
    },
    "content": {
        "name": "Video, streaming and gaming",
        "path": "/content-sharing-reinstatement/",
        "icon": "play",
        "h1": "Content, streaming and gaming account reinstatement",
        "meta_title": "YouTube, Twitch, Vimeo & Xbox Account Reinstatement Help",
        "meta_desc": "Channel terminated, strike received or console account suspended? Case review and appeal preparation for YouTube, Twitch, Vimeo and Xbox accounts.",
        "lede": "Channels, streams and gaming profiles. Strike systems, copyright claims and termination appeals, handled with the one-shot nature of many of these appeals in mind.",
    },
    "gig": {
        "name": "Gig, travel and freelance",
        "path": "/gig-and-freelance-reinstatement/",
        "icon": "briefcase",
        "h1": "Gig, travel and freelance account reinstatement",
        "meta_title": "Driver, Host & Freelancer Deactivation Appeals | Reinstatement",
        "meta_desc": "Deactivated on Uber, Lyft, DoorDash, Instacart, Airbnb, Booking.com, Upwork or Fiverr? Evidence-led appeals for accounts that pay your income.",
        "lede": "Accounts that are your income: drivers, couriers, shoppers, hosts and freelancers. Deactivation reviews, background check disputes, and earnings or payout holds.",
    },
    "email": {
        "name": "Email and core accounts",
        "path": "/email-account-reinstatement/",
        "icon": "mail",
        "h1": "Email and core account recovery",
        "meta_title": "Disabled Gmail & Yahoo Mail Account Recovery Help",
        "meta_desc": "Google Account disabled or Yahoo Mail locked? Structured appeals and recovery steps for the accounts that unlock everything else you use.",
        "lede": "The account that receives every password reset. When it is disabled, everything else is at risk. We help you use the provider's recovery and appeal routes correctly.",
    },
}

# Shared FAQ appended to every platform page.
COMMON_FAQ = [
    ("Are you affiliated with {name}?",
     "No. We are an independent case preparation service. We have no special access to {name}, no internal contacts, and we do not claim otherwise. Every submission goes through the official route {name} provides to account holders."),
    ("Can you guarantee reinstatement?",
     "No one can honestly guarantee a platform decision. What we control is the quality of the diagnosis, the evidence and the written response. We tell you at the review stage if we think the case is weak, and why."),
]

P = []


def add(**kw):
    kw.setdefault("legacy", False)
    kw.setdefault("funds", "")
    kw.setdefault("related", [])
    P.append(kw)


# ---------------------------------------------------------------- Marketplaces
add(
    id="amazon", legacy=True, cat="commerce", name="Amazon",
    path="/amazon-reinstatement/",
    h1="Amazon seller account reinstatement",
    meta_title="Amazon Seller Account Reinstatement & Plan of Action Service",
    meta_desc="Amazon seller account deactivated or at risk? Root cause analysis, Plan of Action writing and evidence packs built for the Account Health appeal route.",
    lede="Deactivated, suspended or sitting in At Risk status on Seller Central? We diagnose the real root cause, write a Plan of Action that answers what Seller Performance asked, and assemble evidence it can verify.",
    accounts="Seller Central accounts on Professional or Individual plans, Brand Registry owners, and accounts deactivated for related-account or verification reasons.",
    triggers=[
        "Order Defect Rate, Late Shipment Rate or Pre-fulfillment Cancel Rate outside Amazon's published targets",
        "Intellectual property complaints: trademark, copyright, patent or counterfeit allegations",
        "Inauthentic item complaints where invoices or supply chain documents are requested",
        "Seller identity or business verification failures, including mismatched documents",
        "Related-account findings linking you to a previously deactivated account",
        "Product safety, restricted product or listing policy violations",
        "Review manipulation or incentivized review findings",
    ],
    notices=[
        ("Your Amazon selling privileges have been deactivated",
         "The account cannot sell. Funds are usually held while the appeal is open. The notice names the policy area and what Amazon wants to see."),
        ("Your account is at risk of deactivation",
         "Your Account Health Rating has dropped into the At Risk band. There is still time to act before a deactivation."),
        ("We have received a report of intellectual property infringement",
         "A rights owner has filed a complaint. The fastest route is often a retraction from the complainant, where the facts support one."),
        ("Supply chain documentation requested",
         "Amazon wants invoices that tie your stock to a verifiable source. Receipts and marketplace purchases rarely pass."),
    ],
    route=[
        "Open Account Health in Seller Central and read every linked policy and the exact request.",
        "Identify the true root cause from your order data, listings, supplier records and account history.",
        "Submit the appeal through Account Health (Reactivate your account) with a Plan of Action and supporting documents.",
        "Respond to follow-up requests in the same case thread, answering precisely what was asked.",
    ],
    evidence=[
        "Supplier invoices with matching business names, addresses and quantities",
        "Letters of authorization and brand authorization, where relevant",
        "Order-level data showing corrective action was taken",
        "Updated internal procedures (SOPs) and staff training records",
        "Identity and business registration documents that match the account exactly",
    ],
    funds="Amazon typically holds a deactivated account's balance while an appeal is open. If the account is not reinstated, the remaining balance is handled under the Amazon Services Business Solutions Agreement, which is why the disbursement request and the appeal should be planned together.",
    faqs=[
        ("What makes an Amazon Plan of Action succeed?",
         "Specificity. Seller Performance looks for the root cause in your operation, the corrective actions already taken, and preventive measures that stop it happening again. Generic apologies and promises are the most common reason appeals are rejected."),
        ("Should I submit a new appeal after a rejection?",
         "Not immediately. Each rejection adds to the case history. Read the reply, work out what was missing, and only resubmit when you can add something materially new."),
        ("Can you help with an IP complaint rather than a full deactivation?",
         "Yes. Many IP cases are best resolved by a retraction from the rights owner or by proving authenticity. We prepare the approach and the documents; where a legal dispute exists, you should involve an attorney."),
    ],
    sources=[("Amazon Seller Central", "https://sellercentral.amazon.com/")],
    related=["walmart", "ebay", "shopify"],
)

add(
    id="ebay", legacy=True, cat="commerce", name="eBay",
    path="/ebay-reinstatement/",
    h1="eBay seller account reinstatement",
    meta_title="eBay Account Reinstatement & Suspension Review Service",
    meta_desc="eBay account suspended, restricted or funds on hold? Independent review of the notice, evidence preparation and appeal support for eBay sellers and buyers.",
    lede="Suspended, restricted or placed under selling limits on eBay? We read the notice against eBay's policies and your seller performance, then prepare a factual response through eBay's own channels.",
    accounts="Business and private seller accounts, and buyer accounts restricted for payment or policy reasons.",
    triggers=[
        "Seller performance falling below eBay's standards (transaction defects, late shipments, cases closed without resolution)",
        "Linked-account findings connecting you to a suspended account",
        "Prohibited or restricted item listings, or intellectual property reports via VeRO",
        "Identity or payment verification problems with managed payments",
        "Unpaid seller fees or buyer protection case outcomes",
    ],
    notices=[
        ("Your account has been suspended",
         "Selling and sometimes buying is blocked. The message usually points to a policy area. Some suspensions are indefinite."),
        ("Selling restrictions have been placed on your account",
         "You can still trade, within limits. The fix is usually operational and measured through seller performance."),
        ("Your funds are on hold",
         "Payouts are delayed, often because the account is new, a case is open, or there is a risk signal on recent sales."),
    ],
    route=[
        "Read the notice and your Seller Dashboard to pin down the policy area and metrics involved.",
        "Correct what can be corrected first: listings, handling times, unresolved cases, fees.",
        "Respond through the channel stated in the notice with a factual, documented explanation.",
        "Track payout status and hold releases separately from the account decision.",
    ],
    evidence=[
        "Proof of shipment and delivery tracking for disputed orders",
        "Supplier invoices for items reported as counterfeit or infringing",
        "Identity and bank documents that match account details",
        "A written account of corrective steps and dates",
    ],
    funds="eBay can hold payouts for reasons including new-seller status, open cases or risk signals. Holds are usually released once the condition is met, so we identify which condition applies before anything is submitted.",
    faqs=[
        ("Is an eBay suspension permanent?",
         "Some are time limited, some indefinite. The wording of your notice matters, and so does the history on the account. We tell you what we see after reading it."),
        ("Can I open another eBay account while suspended?",
         "No. Opening a new account while suspended breaches eBay's policies and usually makes the original case unrecoverable. We do not assist with it."),
        ("What about a VeRO takedown?",
         "VeRO reports come from rights owners. The practical route is often contacting the rights owner to request a retraction, supported by evidence of authenticity."),
    ],
    sources=[("eBay policies", "https://www.ebay.com/help/policies")],
    related=["amazon", "paypal", "etsy"],
)

add(
    id="walmart", legacy=True, cat="commerce", name="Walmart",
    path="/walmart-reinstatement/",
    h1="Walmart Marketplace seller account reinstatement",
    meta_title="Walmart Seller Account Reinstatement & Appeal Preparation",
    meta_desc="Walmart Marketplace account suspended or unpublished? Performance analysis, action plan writing and appeal preparation built around Walmart's seller standards.",
    lede="Walmart Marketplace suspensions usually follow performance standards, listing compliance or verification problems. We map the notice to the data and prepare an action plan that answers it.",
    accounts="Walmart Marketplace sellers in the United States, including Walmart Fulfillment Services users.",
    triggers=[
        "On-time delivery, valid tracking, cancellation or return metrics outside Walmart's seller performance standards",
        "Prohibited products, pricing policy issues or listing quality violations",
        "Intellectual property or authenticity complaints",
        "Business verification and tax identity mismatches",
        "Customer experience issues surfaced through complaints and returns",
    ],
    notices=[
        ("Your account has been suspended",
         "Items are unpublished and new orders stop. The notice usually cites a performance area or policy."),
        ("Performance warning",
         "A standard is slipping. This is the moment to fix the process and document it, before a suspension."),
    ],
    route=[
        "Pull the performance dashboard and order data for the period the notice refers to.",
        "Identify the operational root cause: carrier, handling time, inventory sync, or listing data.",
        "Submit an action plan through the route stated in the notice or Seller Center case management.",
        "Monitor metrics after reinstatement, because the same standard applies from day one.",
    ],
    evidence=[
        "Order-level shipping and tracking exports",
        "Carrier or 3PL correspondence showing fixes",
        "Supplier invoices and authorization for brand items",
        "Business registration and tax documents",
    ],
    faqs=[
        ("How is a Walmart action plan different from an Amazon Plan of Action?",
         "The structure is similar: root cause, corrective actions, prevention. Walmart cases lean heavily on measurable performance, so the plan needs numbers from your own data."),
        ("Will my listings come back automatically?",
         "Usually not all at once. After reinstatement, check listing status and inventory feeds item by item."),
    ],
    sources=[("Walmart Marketplace Learn", "https://marketplacelearn.walmart.com/")],
    related=["amazon", "wayfair", "lowes"],
)

add(
    id="etsy", legacy=True, cat="commerce", name="Etsy",
    path="/etsy-reinstatement/",
    h1="Etsy shop suspension and reinstatement",
    meta_title="Etsy Shop Suspended? Reinstatement & Appeal Support",
    meta_desc="Etsy shop suspended, payment account on reserve or listings removed? We review the notice against Etsy's seller policy and prepare a factual appeal.",
    lede="Etsy suspensions affect makers, curators and print-on-demand sellers alike. We look at the policy cited, your shop's production disclosures and your case history before anything is written.",
    accounts="Etsy shops of any size, including those using production partners.",
    triggers=[
        "Creativity standards or production partner disclosure issues",
        "Intellectual property reports from rights owners",
        "Order fulfillment and case resolution problems",
        "Payment account verification or reserve reviews",
        "Prohibited items policy violations",
    ],
    notices=[
        ("Your shop has been suspended",
         "The shop is not visible and cannot sell. Some notices invite an appeal, some describe a final decision."),
        ("A reserve has been placed on your payment account",
         "Part of your sales balance is held for a period to cover potential refunds or cases."),
    ],
    route=[
        "Identify the exact policy and listings involved.",
        "Fix disclosures, remove or amend affected listings, resolve open cases.",
        "Reply through the Etsy channel stated in the notice with a clear account of what changed.",
    ],
    evidence=[
        "Photos and records of your making process",
        "Production partner details and agreements",
        "License agreements for licensed designs",
        "Order and shipping records for disputed orders",
    ],
    funds="Etsy can place reserves on payment accounts. A reserve is not a suspension, and it has its own release conditions, which we identify from the notice.",
    faqs=[
        ("My shop was suspended with no clear reason. What now?",
         "We work from what you do have: recent listings, recent cases, recent reports and account changes. The likely trigger is usually visible in the data."),
    ],
    sources=[("Etsy seller policy", "https://www.etsy.com/legal/sellers/")],
    related=["shopify", "ebay", "amazon"],
)

add(
    id="wayfair", legacy=True, cat="commerce", name="Wayfair",
    path="/wayfair-reinstatement/",
    h1="Wayfair supplier account reinstatement",
    meta_title="Wayfair Supplier Account Suspension & Reinstatement Help",
    meta_desc="Wayfair supplier account suspended or products deactivated? We analyze Partner Home performance data and prepare a structured corrective action response.",
    lede="Wayfair works with suppliers rather than open marketplace sellers, so performance and compliance problems are handled through your Partner Home relationship. We prepare the data and the written response.",
    accounts="Wayfair suppliers, including drop-ship and CastleGate users.",
    triggers=[
        "Order fulfillment, damage or return rates outside expectations",
        "Product compliance, safety or content accuracy problems",
        "Inventory feed errors leading to cancellations",
    ],
    notices=[
        ("Your products have been deactivated",
         "Listings are removed from sale, often while a quality or compliance problem is reviewed."),
    ],
    route=[
        "Export the relevant Partner Home performance data.",
        "Identify the process failure behind the metric.",
        "Send a corrective action plan through your supplier support channel.",
    ],
    evidence=[
        "Packaging and damage prevention changes with photos",
        "Product compliance certificates and test reports",
        "Inventory feed logs and fixes",
    ],
    faqs=[
        ("Can a Wayfair supplier relationship be restored after deactivation?",
         "It depends on the reason and the history. Operational failures with a credible fix are the most recoverable."),
    ],
    sources=[("Wayfair Partner Home", "https://partners.wayfair.com/")],
    related=["walmart", "lowes", "amazon"],
)

add(
    id="lowes", legacy=True, cat="commerce", name="Lowe's",
    path="/lowes-reinstatement/",
    h1="Lowe's Marketplace seller account reinstatement",
    meta_title="Lowe's Marketplace Seller Suspension & Reinstatement Help",
    meta_desc="Lowe's Marketplace seller account suspended or offers deactivated? Performance review and corrective action preparation for home improvement sellers.",
    lede="Lowe's online marketplace holds third-party sellers to performance, product compliance and customer service expectations. We turn a suspension notice into a clear corrective plan.",
    accounts="Third-party sellers on the Lowe's online marketplace.",
    triggers=[
        "Late shipment, cancellation or return performance",
        "Product safety and compliance documentation gaps",
        "Customer messaging response times",
    ],
    notices=[
        ("Your shop has been suspended",
         "Offers are removed while performance or compliance is reviewed."),
    ],
    route=[
        "Review the marketplace performance dashboard for the notice period.",
        "Fix the underlying process and document it.",
        "Respond through the seller support channel named in the notice.",
    ],
    evidence=[
        "Order and shipping data exports",
        "Product compliance documents",
        "Customer service response logs",
    ],
    faqs=[
        ("Is Lowe's Marketplace handled like Amazon?",
         "The principles are the same, but each marketplace has its own standards and support process. We work from the Lowe's notice and policies, not a generic template."),
    ],
    sources=[("Lowe's", "https://www.lowes.com/")],
    related=["wayfair", "walmart", "amazon"],
)

add(
    id="stockx", legacy=True, cat="commerce", name="StockX",
    path="/stockx-reinstatement/",
    h1="StockX seller account reinstatement and penalty fee appeals",
    meta_title="StockX Seller Account Reinstatement & Penalty Fee Appeals",
    meta_desc="StockX seller account suspended or hit with penalty fees for failed verification, late shipping or cancellations? We prepare evidence-led appeals and disputes.",
    lede="StockX sellers face penalty fees and suspensions tied to verification outcomes, shipping times and cancellations. We review each penalty on the facts and prepare the dispute or appeal.",
    accounts="StockX sellers at any seller level, including high-volume and consignment sellers.",
    triggers=[
        "Items failing StockX verification (authenticity or condition)",
        "Shipping after the required window",
        "Cancelling sales after an ask was matched",
        "Repeated penalties leading to account suspension",
    ],
    notices=[
        ("Your item failed verification",
         "A penalty fee may apply and the item is returned. Repeated failures can lead to suspension."),
        ("Your account has been suspended",
         "Selling is blocked. Pending payouts may be held."),
    ],
    route=[
        "List every penalty with order number, date and stated reason.",
        "Separate penalties that are disputable on facts from those that are not.",
        "Submit disputes and the account appeal through StockX support with evidence per order.",
    ],
    evidence=[
        "Purchase receipts and supplier invoices for the item",
        "Photos and video of condition and packaging at dispatch",
        "Carrier scans and timestamps",
    ],
    faqs=[
        ("Can penalty fees be refunded?",
         "Where the facts show the penalty was applied in error, for example a carrier delay or a verification dispute with strong evidence, a dispute is worth making. We will tell you which ones are not."),
    ],
    sources=[("StockX Help", "https://stockx.com/help")],
    related=["ebay", "amazon", "paypal"],
)

add(
    id="shopify", cat="commerce", name="Shopify",
    path="/shopify-reinstatement/",
    h1="Shopify store and Shopify Payments reinstatement",
    meta_title="Shopify Store Closed or Payments Disabled? Reinstatement Help",
    meta_desc="Shopify store frozen, closed or Shopify Payments disabled with payouts held? Acceptable Use review, risk evidence and appeal preparation.",
    lede="Shopify can close a store under its Acceptable Use Policy, or disable Shopify Payments after a risk review while the store stays open. These are different problems with different routes.",
    accounts="Shopify merchants on any plan, including those using Shopify Payments.",
    triggers=[
        "Acceptable Use Policy findings: restricted products, misleading claims or intellectual property",
        "Shopify Payments risk reviews: high chargebacks, fulfillment delays, unusual volume",
        "Identity or business verification gaps",
    ],
    notices=[
        ("Your store has been closed",
         "The storefront is offline. The notice usually cites the Acceptable Use Policy."),
        ("Shopify Payments has been disabled",
         "Card processing stops and payouts may be held while risk is assessed."),
    ],
    route=[
        "Determine whether the store, the payment account, or both are affected.",
        "Collect fulfillment proof, supplier evidence and policy fixes.",
        "Respond through Shopify Support or the review route named in the notice.",
    ],
    evidence=[
        "Fulfillment and delivery records for recent orders",
        "Chargeback responses and outcomes",
        "Supplier invoices and product documentation",
        "Updated storefront policies and product claims",
    ],
    funds="If Shopify Payments is disabled, payouts may be held for a period to cover refunds and chargebacks. We plan the release request alongside any appeal.",
    faqs=[
        ("Can I switch to another payment processor while Shopify reviews my account?",
         "Sometimes, but it depends on why Shopify Payments was disabled. If the underlying risk is not addressed, other processors will often find the same problem."),
    ],
    sources=[("Shopify Acceptable Use Policy", "https://www.shopify.com/legal/aup")],
    related=["stripe", "paypal", "etsy"],
)

add(
    id="tiktok-shop", cat="commerce", name="TikTok Shop",
    path="/tiktok-shop-reinstatement/",
    h1="TikTok Shop seller account reinstatement",
    meta_title="TikTok Shop Seller Account Suspended? Appeal Preparation",
    meta_desc="TikTok Shop seller account suspended, violation points issued or settlements held? Policy review and appeal preparation built on Seller Center data.",
    lede="TikTok Shop enforces seller policies through violations recorded in Seller Center. We read each violation, challenge the ones that are wrong, and fix the operations behind the rest.",
    accounts="TikTok Shop sellers and brands in the United States.",
    triggers=[
        "Late dispatch, cancellations or poor fulfillment performance",
        "Prohibited or restricted products and misleading product claims",
        "Intellectual property reports",
        "Creator or affiliate content that breaches shop policy",
    ],
    notices=[
        ("Your shop has been deactivated",
         "Selling stops. Settlements may be held while the case is open."),
        ("Violation recorded",
         "A policy breach has been logged against the shop, which can escalate if repeated."),
    ],
    route=[
        "Export the violation list and supporting order data from Seller Center.",
        "Appeal each disputable violation within the window shown in Seller Center.",
        "Submit the account appeal with corrective actions for the remainder.",
    ],
    evidence=[
        "Dispatch and tracking records",
        "Product documentation and claim substantiation",
        "Brand authorization for branded items",
    ],
    faqs=[
        ("Should I appeal every violation?",
         "Only the ones you can support with evidence. Weak appeals waste the window and add to the record."),
    ],
    sources=[("TikTok Shop Seller Center", "https://seller-us.tiktok.com/")],
    related=["tiktok", "amazon", "shopify"],
)

# ---------------------------------------------------------------- Payments
add(
    id="paypal", legacy=True, cat="payments", name="PayPal",
    path="/paypal-reinstatement/",
    h1="PayPal account limitation and reinstatement",
    meta_title="PayPal Account Limited or Permanently Limited? Help & Appeals",
    meta_desc="PayPal account limited, permanently limited or funds on hold? We classify the limitation, prepare documents and plan the 180-day hold release.",
    lede="A PayPal limitation can mean anything from a missing document to a permanent closure with funds held. We work out which one you have and what PayPal actually needs to see.",
    accounts="Personal and business PayPal accounts, including Venmo business profiles where PayPal policy applies.",
    triggers=[
        "Identity, business or source-of-funds verification requests",
        "Acceptable Use Policy concerns about products or business model",
        "Elevated disputes, chargebacks or claims",
        "Unusual transaction patterns compared with account history",
        "Links to other limited accounts",
    ],
    notices=[
        ("Your account access has been limited",
         "Some functions are restricted until you provide information. This is often recoverable."),
        ("We have permanently limited your account",
         "PayPal has decided to end the relationship. The focus shifts to releasing your balance."),
        ("Your funds are on hold",
         "A specific payment or balance is held, usually tied to delivery confirmation or dispute risk."),
    ],
    route=[
        "Open the Resolution Center and list every open task and request.",
        "Upload documents that match the account exactly, in the format requested.",
        "Where appropriate, send a clear written explanation through the Message Center.",
        "For permanent limitations, track the hold period and the conditions for release.",
    ],
    evidence=[
        "Government ID and proof of address matching the account name",
        "Business registration and bank statements",
        "Supplier invoices and proof of delivery for disputed sales",
        "Website terms, refund policy and product descriptions",
    ],
    funds="Under the PayPal User Agreement, PayPal may hold funds after a permanent limitation, commonly for up to 180 days, to cover disputes and reversals. The release is often a separate process from any appeal.",
    faqs=[
        ("Can a permanently limited PayPal account be reinstated?",
         "It is rare but not impossible, usually where the decision rested on a clear factual error. More often the realistic goal is releasing the funds on time."),
        ("Why does PayPal keep asking for the same document?",
         "Usually because the document does not match: a different name format, an old address, or a cropped image. We check every document before it is sent."),
    ],
    sources=[("PayPal User Agreement", "https://www.paypal.com/us/legalhub/useragreement-full")],
    related=["stripe", "ebay", "wise"],
)

add(
    id="stripe", legacy=True, cat="payments", name="Stripe",
    path="/stripe-reinstatement/",
    h1="Stripe account reinstatement and held funds",
    meta_title="Stripe Account Closed or Funds Held? Review & Appeal Help",
    meta_desc="Stripe account under review, rejected or closed with payouts held? We map the decision to Stripe's policies and prepare the review and release request.",
    lede="Stripe decisions usually rest on risk: your business model, your dispute history or information Stripe could not verify. We rebuild the picture Stripe is looking at and answer it.",
    accounts="Stripe accounts for businesses, platforms and Connect accounts.",
    triggers=[
        "Business type on the Restricted Businesses list or unclear business model",
        "High dispute or refund rates",
        "Information that could not be verified during onboarding or later reviews",
        "Sudden changes in volume or ticket size",
    ],
    notices=[
        ("We are unable to continue supporting your business",
         "The account is being closed. Payouts may continue on a schedule or be held for a period."),
        ("Additional information required",
         "Stripe needs documents before it can continue processing. Deadlines matter here."),
    ],
    route=[
        "Read the Dashboard notices and any emails in full, noting deadlines.",
        "Classify the issue: verification, business model, or risk.",
        "Respond through the Dashboard or Stripe support with documents and a precise explanation.",
    ],
    evidence=[
        "Business registration and ownership documents",
        "Website, terms of service and refund policy that match what you sell",
        "Fulfillment and delivery evidence",
        "Dispute outcomes and prevention measures",
    ],
    funds="Stripe may hold funds or apply a reserve to cover refunds and disputes after a closure, for a period set out in the Stripe Services Agreement and your notice. Knowing the date and conditions lets you plan cash flow.",
    faqs=[
        ("Can Stripe reverse a closure?",
         "Sometimes, particularly where the closure followed information Stripe could not verify. If your business is on the restricted list, the realistic goal is usually an orderly release of funds."),
    ],
    sources=[
        ("Stripe Services Agreement", "https://stripe.com/legal/ssa"),
        ("Stripe restricted businesses", "https://stripe.com/legal/restricted-businesses"),
    ],
    related=["paypal", "shopify", "wise"],
)

add(
    id="wise", cat="payments", name="Wise",
    path="/wise-reinstatement/",
    h1="Wise account deactivation and held balances",
    meta_title="Wise Account Deactivated or Money Held? Recovery Help",
    meta_desc="Wise account deactivated or under review with a balance held? We help you respond to verification requests and use the official route to return your money.",
    lede="Wise deactivations are usually driven by verification and compliance reviews. The priority is responding accurately, then getting any balance returned through the route Wise provides.",
    accounts="Wise personal and business accounts.",
    triggers=[
        "Enhanced verification or source-of-funds requests",
        "Payment activity inconsistent with the stated account purpose",
        "Sanctions or high-risk jurisdiction screening flags",
    ],
    notices=[
        ("We have deactivated your account",
         "You cannot use the account. Wise normally explains how any balance will be returned."),
    ],
    route=[
        "Answer outstanding verification requests fully and consistently.",
        "Provide a bank account in your name for the return of funds when asked.",
        "Keep all correspondence in the Wise help channels.",
    ],
    evidence=[
        "ID and proof of address",
        "Source-of-funds documents: payslips, invoices, contracts",
        "Business documents for business accounts",
    ],
    funds="When Wise closes an account it typically returns remaining balances to an account in the holder's name after its checks are complete. We help you get the documents right the first time.",
    faqs=[
        ("Will Wise explain why my account was deactivated?",
         "Financial services providers are often limited in what they can disclose about compliance decisions. We focus on what they have asked for and on returning your money."),
    ],
    sources=[("Wise Help Centre", "https://wise.com/help/")],
    related=["payoneer", "paypal", "coinbase"],
)

add(
    id="payoneer", cat="payments", name="Payoneer",
    path="/payoneer-reinstatement/",
    h1="Payoneer account blocked or funds held",
    meta_title="Payoneer Account Blocked? Held Funds & Verification Help",
    meta_desc="Payoneer account blocked, under compliance review or with funds held? We prepare verification documents and structured responses through official channels.",
    lede="Payoneer is widely used by marketplace sellers and freelancers, which means a block can freeze income from several platforms at once. We prepare the verification and source-of-funds response.",
    accounts="Payoneer accounts for freelancers, marketplace sellers and businesses.",
    triggers=[
        "Know your customer and document verification",
        "Incoming payments from sources that need explanation",
        "Mismatch between account details and marketplace or bank details",
    ],
    notices=[
        ("Your account is blocked",
         "Transfers in and out are restricted while a review is completed."),
    ],
    route=[
        "Complete every verification request in the account.",
        "Explain each payment source with contracts or marketplace statements.",
        "Follow up through Payoneer support in writing.",
    ],
    evidence=[
        "Marketplace payout statements",
        "Client contracts and invoices",
        "ID and business documents",
    ],
    funds="Blocked balances are usually released once the review ends, either to the account or to a verified bank account. The documents decide how quickly.",
    faqs=[
        ("Does a Payoneer block affect my marketplace accounts?",
         "It can interrupt payouts. Tell each marketplace promptly if you need to change the payout method."),
    ],
    sources=[("Payoneer legal", "https://www.payoneer.com/legal/")],
    related=["wise", "upwork", "amazon"],
)

add(
    id="coinbase", cat="payments", name="Coinbase",
    path="/coinbase-reinstatement/",
    h1="Coinbase account restricted or disabled",
    meta_title="Coinbase Account Restricted or Disabled? Recovery Help",
    meta_desc="Coinbase account restricted, disabled or stuck in verification? We help you respond to requests and use the official route to access or withdraw your assets.",
    lede="Coinbase restrictions range from a pending identity check to a closed account. Where the account will not be restored, the aim is secure access to withdraw your assets.",
    accounts="Coinbase retail and business accounts.",
    triggers=[
        "Identity verification failures or document mismatches",
        "Transactions flagged by compliance screening",
        "Suspected account compromise",
    ],
    notices=[
        ("Your account has been restricted",
         "Some features, such as sending or buying, are limited."),
        ("Your account is no longer eligible",
         "Coinbase has decided to close the relationship. Withdrawal routes become the focus."),
    ],
    route=[
        "Secure the account and your email first if compromise is possible.",
        "Complete identity verification exactly as requested.",
        "Contact Coinbase support through the official help center only.",
    ],
    evidence=[
        "ID and proof of address",
        "Source of funds for large deposits",
        "Explanations for flagged transactions",
    ],
    funds="Closed accounts are typically given a route to withdraw remaining assets. Scam recovery services that ask for fees to release crypto are a common fraud: Coinbase support never needs a third party to unlock funds.",
    faqs=[
        ("Someone offered to unlock my Coinbase account for a fee. Is that real?",
         "Treat it as a scam. Nobody outside Coinbase can unlock an account. We only help you prepare what Coinbase asks for, through Coinbase's own channels."),
    ],
    sources=[("Coinbase Help", "https://help.coinbase.com/")],
    related=["wise", "paypal", "gmail"],
)

# ---------------------------------------------------------------- Social
add(
    id="facebook", legacy=True, cat="social", name="Facebook",
    path="/facebook-reinstatement/",
    h1="Facebook account reinstatement",
    meta_title="Facebook Account Disabled or Suspended? Reinstatement Help",
    meta_desc="Facebook account disabled, suspended or hacked? Case review and appeal preparation using Meta's own routes, including the 180-day disagree window.",
    lede="Meta's systems suspend and disable Facebook accounts at scale, and many decisions are automated. We help you use the right route: appeal, identity check, hacked account recovery or content review.",
    accounts="Personal profiles, Pages and the accounts that administer business assets.",
    triggers=[
        "Community Standards findings on posts, comments or messages",
        "Suspected fake or impersonating profiles, or name policy issues",
        "Account compromise followed by abusive activity",
        "Automated detection of spam-like behavior",
        "Age or identity verification not completed",
    ],
    notices=[
        ("We suspended your account",
         "Meta's notices typically give a window, commonly 180 days, to disagree with the decision before the account is permanently disabled."),
        ("Your account has been disabled",
         "A final decision after the review window, or for severe violations. Options are narrower."),
        ("Confirm your identity",
         "Meta needs an ID or video selfie check. Many accounts are recovered at this step."),
    ],
    route=[
        "Log in and follow the on-screen steps first: identity checks and the disagree option.",
        "If the account was hacked, use Meta's hacked account recovery route.",
        "Keep the explanation factual and specific to the violation cited.",
        "For content decisions, check whether an Oversight Board referral is available.",
    ],
    evidence=[
        "Government ID matching the profile name",
        "Evidence of compromise: login alert emails, changed email or phone",
        "Screenshots of the enforcement notice and dates",
    ],
    faqs=[
        ("How long do I have to appeal a Facebook suspension?",
         "The notice states the window. It is commonly 180 days to disagree with a suspension. Do not wait: the earlier the review, the fresher the evidence."),
        ("Does Meta Verified help?",
         "It gives access to account support, which can help with some recovery cases. It does not overturn policy decisions by itself."),
    ],
    sources=[
        ("Meta Community Standards", "https://transparency.meta.com/policies/community-standards/"),
        ("Facebook Help Center", "https://www.facebook.com/help/"),
    ],
    related=["instagram", "meta-ads", "twitter"],
)

add(
    id="instagram", legacy=True, cat="social", name="Instagram",
    path="/instagram-reinstatement/",
    h1="Instagram account reinstatement",
    meta_title="Instagram Account Suspended or Disabled? Reinstatement Help",
    meta_desc="Instagram account suspended, disabled or hacked? We help you use Meta's appeal, identity and recovery routes correctly, with a clear case built on the notice.",
    lede="Instagram suspensions often follow automated detection, age checks or account compromise. We identify which one you face and prepare the response Meta can act on.",
    accounts="Personal, creator and business Instagram accounts.",
    triggers=[
        "Community Guidelines findings on posts, stories, reels or messages",
        "Age verification requests that were not completed",
        "Suspected impersonation or inauthentic behavior",
        "Automation, follow or like tools",
        "Compromise leading to spam or scams from your account",
    ],
    notices=[
        ("We suspended your account",
         "You typically have a window to disagree before the account is disabled permanently."),
        ("Confirm you are human or confirm your age",
         "Complete the check. Skipping it usually leads to a disabled account."),
    ],
    route=[
        "Follow the in-app steps first, including identity or age checks.",
        "For hacked accounts, use Instagram's hacked account route.",
        "Submit a clear, factual disagreement tied to the cited guideline.",
    ],
    evidence=[
        "ID matching the account name, where requested",
        "Proof that you control the linked email and phone",
        "Login alert emails showing compromise",
    ],
    faqs=[
        ("Can a disabled Instagram account be recovered after the window closes?",
         "Options narrow sharply. Some cases progress through Meta support channels, but you should expect lower odds."),
        ("Is it safe to use follower growth tools?",
         "Automation that breaches Instagram's terms is a common cause of suspension. We will not help you continue using it."),
    ],
    sources=[
        ("Instagram Community Guidelines", "https://help.instagram.com/477434105621119"),
        ("Instagram Help Center", "https://help.instagram.com/"),
    ],
    related=["facebook", "tiktok", "meta-ads"],
)

add(
    id="twitter", legacy=True, cat="social", name="X (Twitter)",
    path="/twitter-reinstatement/",
    h1="X (Twitter) account reinstatement",
    meta_title="Twitter / X Account Suspended? Appeal & Reinstatement Help",
    meta_desc="X (Twitter) account suspended, locked or in read-only mode? We identify the enforcement type and prepare a focused appeal through X's official forms.",
    lede="X uses locks, read-only limits and suspensions, and each has a different fix. We tell you which one you have and prepare the appeal that matches it.",
    accounts="Personal, creator and organization accounts on X.",
    triggers=[
        "Rules violations: abuse, hateful conduct, platform manipulation or spam",
        "Suspected impersonation or misleading identity",
        "Automated behavior or bulk actions",
        "Account compromise",
    ],
    notices=[
        ("Your account is suspended",
         "The account is not usable. An appeal form is available."),
        ("Your account is locked",
         "Often cleared by verification steps or deleting a post. Not the same as a suspension."),
        ("Read-only mode",
         "You can browse but not post while a limit applies."),
    ],
    route=[
        "Confirm which enforcement applies from the notice and the account screen.",
        "Complete any unlock steps before appealing.",
        "Submit the appeal through X's help center appeal form, specific to the rule cited.",
    ],
    evidence=[
        "Screenshots of the notice",
        "Context for the post in question",
        "Proof of identity for impersonation claims",
    ],
    faqs=[
        ("How many times can I appeal an X suspension?",
         "There is no benefit in repeated identical appeals. One well-prepared appeal with context carries more weight than several short ones."),
    ],
    sources=[
        ("X Rules", "https://help.x.com/en/rules-and-policies/x-rules"),
        ("X appeals form", "https://help.x.com/en/forms/account-access/appeals"),
    ],
    related=["facebook", "reddit", "tiktok"],
)

add(
    id="tiktok", legacy=True, cat="social", name="TikTok",
    path="/tiktok-reinstatement/",
    h1="TikTok account reinstatement",
    meta_title="TikTok Account Banned or Suspended? Appeal & Reinstatement",
    meta_desc="TikTok account banned, suspended or restricted by strikes? We review the violation history and prepare a precise in-app appeal for creators and businesses.",
    lede="TikTok uses a strike system alongside permanent bans for severe violations. We read your violation history, appeal the decisions that are wrong, and protect the account from further strikes.",
    accounts="Personal, creator and business TikTok accounts.",
    triggers=[
        "Community Guidelines violations accumulating as strikes",
        "Severe violations that lead to an immediate ban",
        "Minimum age concerns",
        "Suspected spam, fake engagement or impersonation",
    ],
    notices=[
        ("Your account was permanently banned",
         "An appeal option is usually shown in the app. The appeal must address the cited violation."),
        ("Your account has been restricted",
         "Some features are limited for a period after violations."),
    ],
    route=[
        "Review Account status in the app for each violation and its status.",
        "Appeal individual content violations that were wrongly applied.",
        "Appeal the account ban in-app with a clear, factual explanation.",
    ],
    evidence=[
        "The original content and its context",
        "Proof of age where age is questioned",
        "Screenshots of violation notices",
    ],
    faqs=[
        ("Do TikTok strikes expire?",
         "TikTok states that strikes expire from your record after a period, generally 90 days. Severe violations can still lead to a ban on a single occurrence."),
    ],
    sources=[("TikTok Community Guidelines", "https://www.tiktok.com/community-guidelines")],
    related=["instagram", "tiktok-shop", "youtube"],
)

add(
    id="reddit", legacy=True, cat="social", name="Reddit",
    path="/reddit-reinstatement/",
    h1="Reddit account suspension and reinstatement",
    meta_title="Reddit Account Suspended? Appeal & Reinstatement Service",
    meta_desc="Reddit account suspended sitewide or banned from a subreddit? We separate admin suspensions from moderator bans and prepare the right appeal.",
    lede="Reddit has two very different enforcement systems: sitewide suspensions by Reddit admins, and subreddit bans by volunteer moderators. The route depends on which one hit you.",
    accounts="Personal and brand accounts on Reddit.",
    triggers=[
        "Content Policy violations such as harassment, spam or vote manipulation",
        "Ban evasion findings linked to another account",
        "Automated spam detection on links or posting patterns",
    ],
    notices=[
        ("Your account has been suspended",
         "A sitewide action by Reddit. Temporary or permanent. Appeals go to Reddit."),
        ("You have been banned from r/...",
         "A moderator decision limited to one community. Appeals go to that community's moderators."),
    ],
    route=[
        "Identify whether the action is sitewide or subreddit-level.",
        "For sitewide suspensions, use Reddit's appeal form.",
        "For subreddit bans, message the moderators respectfully through modmail.",
    ],
    evidence=[
        "The content or activity cited",
        "Context showing no rule was broken",
        "For ban evasion claims, evidence of separate users (for example a shared household network)",
    ],
    faqs=[
        ("Can Reddit admins overturn a subreddit ban?",
         "Generally no. Subreddit bans are moderator decisions. Reddit steps in only where moderators break Reddit's own rules for moderators."),
    ],
    sources=[
        ("Reddit Content Policy", "https://www.redditinc.com/policies/content-policy"),
        ("Reddit appeals", "https://www.reddit.com/appeal"),
    ],
    related=["discord", "twitter", "twitch"],
)

add(
    id="discord", legacy=True, cat="social", name="Discord",
    path="/discord-reinstatement/",
    h1="Discord account suspension and reinstatement",
    meta_title="Discord Account Disabled or Suspended? Appeal Help",
    meta_desc="Discord account disabled, suspended or server removed? We review Account Standing and prepare an appeal tied to Discord's Community Guidelines.",
    lede="Discord records warnings and restrictions in Account Standing, and serious violations can disable an account or remove a server. We help you understand the record and appeal properly.",
    accounts="Personal accounts, server owners and community moderators.",
    triggers=[
        "Community Guidelines violations in messages or servers",
        "Server owner responsibility for community content",
        "Spam, raids or automation",
        "Compromise from token-stealing scams",
    ],
    notices=[
        ("Your account has been disabled",
         "You cannot log in. Appeals go through Discord's support forms."),
        ("A warning has been added to your Account Standing",
         "Restrictions may apply for a period. Repeated warnings escalate."),
    ],
    route=[
        "Review Account Standing for each violation.",
        "Appeal through Discord's in-app route or the Trust and Safety support form.",
        "If compromised, secure email and payment methods before appealing.",
    ],
    evidence=[
        "Message and server context",
        "Evidence of compromise",
        "Moderation logs for server owners",
    ],
    faqs=[
        ("My account was hacked and used to spam. Can it be restored?",
         "Compromise cases are among the more recoverable, provided you can show the takeover and have secured the account."),
    ],
    sources=[("Discord Community Guidelines", "https://discord.com/guidelines")],
    related=["reddit", "twitch", "xbox-live"],
)

add(
    id="linkedin", cat="social", name="LinkedIn",
    path="/linkedin-reinstatement/",
    h1="LinkedIn account restriction and reinstatement",
    meta_title="LinkedIn Account Restricted? Verification & Reinstatement Help",
    meta_desc="LinkedIn account restricted or suspended? We help with identity verification, automation-related restrictions and User Agreement appeals.",
    lede="LinkedIn restrictions most often follow identity doubts or automation. For professionals and sales teams the account is a business asset, so we move carefully and document everything.",
    accounts="Personal profiles, Sales Navigator and Recruiter users, and Page admins.",
    triggers=[
        "Automation, scraping or browser extensions that breach the User Agreement",
        "Identity verification requests",
        "Suspected fake profile or name mismatch",
        "Spam reports on connection requests or messages",
    ],
    notices=[
        ("Your account has been restricted",
         "LinkedIn usually asks for identity verification or an acknowledgement of the User Agreement."),
    ],
    route=[
        "Remove any automation tools before doing anything else.",
        "Complete identity verification through LinkedIn's official process.",
        "Respond to LinkedIn support in writing with a clear, honest account.",
    ],
    evidence=[
        "Government ID matching the profile name",
        "Proof of employment where the profile is questioned",
    ],
    faqs=[
        ("Will LinkedIn restore my connections and messages?",
         "If the restriction is lifted, the account is restored as it was. That is why recovering the original account is worth the effort."),
    ],
    sources=[("LinkedIn User Agreement", "https://www.linkedin.com/legal/user-agreement")],
    related=["upwork", "facebook", "twitter"],
)

add(
    id="snapchat", cat="social", name="Snapchat",
    path="/snapchat-reinstatement/",
    h1="Snapchat account locked or banned",
    meta_title="Snapchat Account Locked or Banned? Recovery & Appeal Help",
    meta_desc="Snapchat account temporarily locked, permanently locked or compromised? We explain the lock type and prepare an appeal through Snap's support routes.",
    lede="Snapchat locks accounts temporarily or permanently. Temporary locks often clear with the right steps; permanent locks need a careful appeal.",
    accounts="Personal and creator Snapchat accounts.",
    triggers=[
        "Community Guidelines violations",
        "Third-party apps or unofficial clients",
        "Compromise and spam from your account",
    ],
    notices=[
        ("Your account is temporarily locked",
         "Remove any third-party apps, wait the stated period, then unlock."),
        ("Your account has been permanently locked",
         "An appeal through Snapchat Support is the remaining route."),
    ],
    route=[
        "Remove third-party apps and plugins.",
        "Try the official unlock page when the lock is temporary.",
        "Contact Snapchat Support with a factual appeal for permanent locks.",
    ],
    evidence=["Account username and linked email", "Evidence of compromise if relevant"],
    faqs=[
        ("Why was my Snapchat locked when I did nothing wrong?",
         "Third-party apps and account compromise are common causes. We check for both first."),
    ],
    sources=[("Snapchat Community Guidelines", "https://values.snap.com/policy/policy-community-guidelines")],
    related=["instagram", "tiktok", "discord"],
)

add(
    id="pinterest", cat="social", name="Pinterest",
    path="/pinterest-reinstatement/",
    h1="Pinterest account suspension and reinstatement",
    meta_title="Pinterest Account Suspended? Appeal & Reinstatement Help",
    meta_desc="Pinterest account suspended for spam or policy reasons? We review the notice and prepare an appeal, including for business accounts that rely on traffic.",
    lede="Pinterest suspensions frequently come from spam detection, which also catches legitimate businesses. We document why your activity is genuine.",
    accounts="Personal and business Pinterest accounts.",
    triggers=[
        "Spam detection on links, pinning volume or redirects",
        "Community guidelines violations",
        "Links to sites flagged for quality or safety",
    ],
    notices=[("Your account has been suspended", "The account and pins are hidden. An appeal is available.")],
    route=[
        "Check the destination domains of your pins for redirects or flags.",
        "Submit an appeal through Pinterest's help center.",
    ],
    evidence=["Website ownership proof", "Content creation records", "Scheduler tool settings"],
    faqs=[
        ("Does using a scheduler cause suspensions?",
         "Approved scheduling partners are fine. Aggressive volume or unofficial tools can trigger spam detection."),
    ],
    sources=[("Pinterest Community Guidelines", "https://policy.pinterest.com/en/community-guidelines")],
    related=["instagram", "google-merchant-center", "etsy"],
)

# ---------------------------------------------------------------- Ads and publishing
add(
    id="google-adsense", legacy=True, cat="ads", name="Google AdSense",
    path="/google-adsense-reinstatement/",
    h1="Google AdSense account reinstatement",
    meta_title="Google AdSense Account Disabled? Invalid Traffic Appeal Help",
    meta_desc="AdSense account disabled for invalid traffic or policy violations? We analyze traffic sources, fix the causes and prepare the AdSense appeal.",
    lede="AdSense accounts are most often disabled for invalid traffic. The appeal has to show where your traffic comes from, what went wrong, and why it will not happen again.",
    accounts="AdSense publishers on websites, blogs and YouTube-linked accounts.",
    triggers=[
        "Invalid traffic: accidental self-clicks, bots, paid traffic of poor quality, or click encouragement",
        "Program policy violations on content or ad placement",
        "Site behavior issues such as misleading navigation around ads",
    ],
    notices=[
        ("Your AdSense account has been disabled due to invalid traffic",
         "Serving stops. Earnings from invalid activity can be withheld. An appeal form is available."),
        ("Policy violation: ad serving limited or disabled on page",
         "A page or site level issue. Fix it and request a review in the Policy center."),
    ],
    route=[
        "Audit analytics for traffic sources, spikes and referrers around the dates in question.",
        "Remove or fix the traffic sources and placements responsible.",
        "Submit the invalid traffic appeal with specific findings, or request review in Policy center for policy issues.",
    ],
    evidence=[
        "Analytics exports by source, country and date",
        "Records of paid traffic campaigns and vendors",
        "Server logs for suspicious spikes",
    ],
    funds="AdSense can withhold earnings associated with invalid traffic. Whether earnings are paid depends on the outcome and on Google's findings.",
    faqs=[
        ("Can I just create a new AdSense account?",
         "No. AdSense terms do not allow new accounts to get around a disabled one. We only work on the original account."),
    ],
    sources=[("AdSense Program policies", "https://support.google.com/adsense/answer/48182")],
    related=["youtube", "google-ads", "google-merchant-center"],
)

add(
    id="google-ads", cat="ads", name="Google Ads",
    path="/google-ads-reinstatement/",
    h1="Google Ads account suspension appeals",
    meta_title="Google Ads Account Suspended? Appeal & Reinstatement Help",
    meta_desc="Google Ads account suspended for circumventing systems, suspicious payments, misrepresentation or unpaid balance? Policy mapping and appeal preparation.",
    lede="Google Ads suspensions are policy decisions against the account, not individual ads. We work out which policy, fix what the reviewer will check, and prepare one strong appeal.",
    accounts="Google Ads accounts for advertisers and agencies, including manager accounts.",
    triggers=[
        "Circumventing systems, including links to previously suspended accounts",
        "Suspicious payment activity or unpaid balance",
        "Misrepresentation or unacceptable business practices",
        "Advertiser verification not completed",
    ],
    notices=[("Your account has been suspended", "No ads run. An appeal is available in the account.")],
    route=[
        "Identify the policy cited and the websites, payment profiles and users linked to the account.",
        "Fix the landing pages, business information and verification gaps first.",
        "Submit the appeal through the account's appeal route or the suspended account form.",
    ],
    evidence=[
        "Business registration and verification documents",
        "Payment card ownership proof",
        "Landing page changes and disclosures",
    ],
    faqs=[
        ("What does circumventing systems mean in practice?",
         "Anything that looks like an attempt to get around enforcement, including new accounts after a suspension. It is one of the harder policies to overturn, so accuracy matters."),
    ],
    sources=[("Google Ads policies", "https://support.google.com/adspolicy/answer/6008942")],
    related=["google-merchant-center", "google-adsense", "meta-ads"],
)

add(
    id="google-merchant-center", cat="ads", name="Google Merchant Center",
    path="/google-merchant-center-reinstatement/",
    h1="Google Merchant Center suspension and misrepresentation",
    meta_title="Google Merchant Center Suspended? Misrepresentation Fix Help",
    meta_desc="Merchant Center suspended for misrepresentation or policy issues? We audit your site and feed against Shopping policies and prepare the review request.",
    lede="Merchant Center misrepresentation is usually about trust signals on your website and feed. We audit what a reviewer sees and fix it before a review is requested.",
    accounts="Merchant Center accounts for online retailers and brands.",
    triggers=[
        "Misrepresentation: unclear business identity, missing policies or unrealistic offers",
        "Unsupported shopping content or product data mismatches",
        "Website needs improvement or checkout problems",
    ],
    notices=[("Your account is suspended", "Products are disapproved and Shopping ads stop. A review can be requested in the account.")],
    route=[
        "Audit the website: business details, contact routes, returns, shipping and checkout.",
        "Audit the feed for price, availability and product data consistency.",
        "Request a review only when the fixes are live and complete.",
    ],
    evidence=[
        "Updated policies and business information on the site",
        "Feed diagnostics after correction",
    ],
    faqs=[
        ("Why should I not keep requesting reviews?",
         "Repeated unsuccessful review requests can trigger a waiting period before another review is allowed. One complete fix is faster than several partial ones."),
    ],
    sources=[("Shopping ads policies", "https://support.google.com/merchants/answer/6149970")],
    related=["google-ads", "shopify", "google-adsense"],
)

add(
    id="meta-ads", cat="ads", name="Meta Ads",
    path="/meta-ads-reinstatement/",
    h1="Meta ad account and Business Portfolio restrictions",
    meta_title="Meta Ad Account Restricted? Business Portfolio Appeal Help",
    meta_desc="Facebook or Instagram ad account restricted, or Business Portfolio disabled? We review Account Quality and prepare a precise request for review.",
    lede="Meta restricts advertising at several levels: ad accounts, Pages, people and Business Portfolios. The fix starts with finding which asset is restricted and why.",
    accounts="Advertisers and agencies using Meta ads across Facebook and Instagram.",
    triggers=[
        "Advertising Standards violations",
        "Suspicious payment or login activity",
        "An admin's personal profile being restricted",
        "Unverified business or identity",
    ],
    notices=[
        ("Your ad account is restricted",
         "Ads stop. Account Quality shows the reason and whether a review can be requested."),
        ("Your ability to advertise has been restricted",
         "The restriction is on a person, which affects every account they manage."),
    ],
    route=[
        "Open Account Quality and map each restricted asset.",
        "Resolve the root cause, such as a restricted admin or unverified business.",
        "Request review for each asset where the option is offered.",
    ],
    evidence=[
        "Business verification documents",
        "Ad creative and landing page records",
        "Payment method ownership",
    ],
    faqs=[
        ("Should I remove the restricted admin from the Business Portfolio?",
         "Sometimes that is the right move, but it can also remove the person best placed to appeal. We look at the whole structure first."),
    ],
    sources=[("Meta Advertising Standards", "https://transparency.meta.com/policies/ad-standards/")],
    related=["facebook", "instagram", "google-ads"],
)

# ---------------------------------------------------------------- Content, streaming, gaming
add(
    id="youtube", legacy=True, cat="content", name="YouTube",
    path="/youtube-reinstatement/",
    h1="YouTube channel reinstatement and strike appeals",
    meta_title="YouTube Channel Terminated? Reinstatement & Strike Appeals",
    meta_desc="YouTube channel terminated, or Community Guidelines or copyright strikes received? Strike analysis and appeal preparation for creators and brands.",
    lede="YouTube channels are terminated after three Community Guidelines strikes within 90 days, for a single severe violation, or for copyright strikes. Each route has its own appeal and they are not forgiving of weak submissions.",
    accounts="Creator, brand and Partner Program channels.",
    triggers=[
        "Community Guidelines strikes accumulating within 90 days",
        "Copyright strikes from takedown requests",
        "Spam, deceptive practices or scams policies",
        "Circumvention through a linked channel",
    ],
    notices=[
        ("Your channel has been terminated",
         "The channel and content are removed. An appeal form is available from the notice."),
        ("Community Guidelines strike",
         "The strike restricts features for a period and expires after 90 days if no further strikes occur."),
        ("Copyright strike",
         "Resolved by a retraction from the claimant, a counter notification or expiry after training."),
    ],
    route=[
        "List every strike, its type, date and the content concerned.",
        "Appeal wrongly applied strikes individually, or seek retractions for copyright strikes.",
        "Submit the channel termination appeal with specific context for the content involved.",
    ],
    evidence=[
        "The original videos and their context (educational, documentary, scientific or artistic)",
        "Licenses for music and footage",
        "Correspondence with copyright claimants",
    ],
    funds="Monetization and Partner Program earnings follow YouTube and AdSense rules. Earnings linked to invalid activity can be withheld.",
    faqs=[
        ("Can I appeal each strike?",
         "Yes, but YouTube limits appeals, typically one per strike. That is the reason to prepare the first one properly."),
        ("Should I file a counter notification for a copyright strike?",
         "Only if you have the rights or a strong fair use position. A counter notification is a legal process and can lead to a lawsuit. Speak to an attorney where the stakes justify it."),
    ],
    sources=[
        ("Community Guidelines strike basics", "https://support.google.com/youtube/answer/2802032"),
        ("YouTube Help", "https://support.google.com/youtube/"),
    ],
    related=["google-adsense", "twitch", "vimeo"],
)

add(
    id="vimeo", legacy=True, cat="content", name="Vimeo",
    path="/vimeo-reinstatement/",
    h1="Vimeo account removal and reinstatement",
    meta_title="Vimeo Account Removed or Suspended? Reinstatement Help",
    meta_desc="Vimeo account removed or suspended for guideline, copyright or usage reasons? We review the notice and prepare an appeal through Vimeo support.",
    lede="Vimeo removes accounts for content that falls outside its guidelines, for copyright complaints and for usage outside the plan's terms. Businesses using Vimeo to host course or product video are hit hardest.",
    accounts="Free and paid Vimeo accounts, including business and course hosts.",
    triggers=[
        "Acceptable use and community guidelines violations",
        "Copyright complaints (DMCA notices)",
        "Usage patterns outside the plan's terms",
    ],
    notices=[("Your account has been removed", "Videos are no longer available. The email explains the reason and whether you can appeal.")],
    route=[
        "Identify the content or usage cited.",
        "Fix the issue or gather rights evidence.",
        "Appeal through Vimeo support with a specific response.",
    ],
    evidence=["Licenses and ownership records", "Plan and usage records"],
    faqs=[
        ("Can I retrieve my videos if the account stays closed?",
         "Ask Vimeo support directly. Keep your own copies of master files regardless of platform."),
    ],
    sources=[("Vimeo Acceptable Use Policy", "https://vimeo.com/help/guidelines")],
    related=["youtube", "twitch", "google-adsense"],
)

add(
    id="twitch", cat="content", name="Twitch",
    path="/twitch-reinstatement/",
    h1="Twitch suspension and ban appeals",
    meta_title="Twitch Account Suspended? Ban Appeal & Reinstatement Help",
    meta_desc="Twitch channel suspended or indefinitely banned? We review the enforcement and prepare an appeal through Twitch's appeals portal.",
    lede="Twitch suspensions can be temporary or indefinite. Streamers need a fast, accurate appeal that addresses the specific moment of stream or chat cited.",
    accounts="Streamers, affiliates and partners.",
    triggers=[
        "Community Guidelines violations during streams or in chat",
        "DMCA notices for music or video",
        "Off-service conduct in severe cases",
    ],
    notices=[("Your account has been suspended", "The notice states duration and reason. An appeal is available.")],
    route=[
        "Obtain the VOD or clip concerned if available.",
        "Submit the appeal through Twitch's appeals portal with timestamps and context.",
    ],
    evidence=["VOD timestamps and clips", "Music licenses"],
    faqs=[
        ("Should I post about my ban publicly before appealing?",
         "It rarely helps the appeal. Prepare the appeal first."),
    ],
    sources=[("Twitch Community Guidelines", "https://safety.twitch.tv/s/article/Community-Guidelines")],
    related=["youtube", "discord", "xbox-live"],
)

add(
    id="xbox-live", legacy=True, cat="content", name="Xbox Live",
    path="/xbox-live-reinstatement/",
    h1="Xbox account suspension and enforcement review",
    meta_title="Xbox Live Account Suspended? Enforcement Review Help",
    meta_desc="Xbox account suspended or enforcement strikes applied? We help you read your enforcement history and request a case review through official channels.",
    lede="Xbox applies enforcement strikes and suspensions under the Community Standards. You can see your history and request a case review through Xbox's enforcement site.",
    accounts="Xbox and Microsoft accounts used for gaming.",
    triggers=[
        "Community Standards violations in chat, messages or content",
        "Cheating, fraud or unauthorized modification",
        "Chargebacks on purchases",
    ],
    notices=[("Your account has been suspended", "Some or all features are blocked for a period. Your enforcement history lists the reason.")],
    route=[
        "Review enforcement history at enforcement.xbox.com.",
        "Request a case review for enforcements you believe were wrong.",
        "Resolve payment issues such as chargebacks directly with Microsoft billing.",
    ],
    evidence=["Captures or context for the content cited", "Purchase records for payment issues"],
    faqs=[
        ("Can a device ban be reviewed?",
         "Device-level enforcement for cheating is hard to overturn. We will say so honestly after reading your history."),
    ],
    sources=[
        ("Xbox Community Standards", "https://www.xbox.com/en-US/legal/community-standards"),
        ("Xbox enforcement", "https://enforcement.xbox.com/"),
    ],
    related=["discord", "twitch", "gmail"],
)

# ---------------------------------------------------------------- Gig, travel, freelance
add(
    id="booking-com", legacy=True, cat="gig", name="Booking.com",
    path="/booking-com-reinstatement/",
    h1="Booking.com partner account reinstatement",
    meta_title="Booking.com Property Closed or Account Suspended? Help",
    meta_desc="Booking.com property closed, partner account suspended or payouts held? We review the notice and prepare a documented response through the Partner Hub.",
    lede="Booking.com can close properties or suspend partner accounts for guest complaints, suspected fraud or policy issues. For hosts it is revenue, so we prepare a clear, documented response.",
    accounts="Property partners and some guest accounts.",
    triggers=[
        "Guest complaints about misrepresentation or cancellation",
        "Payment or fraud concerns",
        "Policy violations on pricing, content or parity",
    ],
    notices=[("Your property has been closed", "The listing is offline. Contact through the extranet or Partner Hub.")],
    route=[
        "Collect booking records, guest messages and property evidence.",
        "Respond through the extranet or Partner Hub in writing.",
    ],
    evidence=["Property photos and ownership or management rights", "Booking and payment records", "Guest correspondence"],
    funds="Payouts through Booking.com payments can be held while a case is reviewed. Track each reservation's payout status.",
    faqs=[
        ("Can guest complaints alone close a property?",
         "Serious or repeated complaints can. The response should address each one with evidence."),
    ],
    sources=[("Booking.com Partner Hub", "https://partner.booking.com/")],
    related=["airbnb", "stripe", "paypal"],
)

add(
    id="airbnb", cat="gig", name="Airbnb",
    path="/airbnb-reinstatement/",
    h1="Airbnb host and guest account reinstatement",
    meta_title="Airbnb Account Suspended or Removed? Reinstatement Help",
    meta_desc="Airbnb host or guest account removed or suspended? We help with background check disputes, policy findings and a documented appeal to Airbnb.",
    lede="Airbnb removes hosts and guests for safety, trust and policy reasons, sometimes based on screening results. We help you understand the notice and respond on the facts.",
    accounts="Hosts, co-hosts and guests.",
    triggers=[
        "Background or identity screening results",
        "Off-platform payment requests or policy breaches",
        "Guest safety complaints or party policy violations",
    ],
    notices=[("Your account has been removed", "The notice explains the category and whether you can ask for a reconsideration.")],
    route=[
        "Read the notice and identify the category.",
        "Where screening is involved, obtain and review the report and dispute errors with the screening provider.",
        "Respond to Airbnb in writing with documents.",
    ],
    evidence=["Screening report and dispute outcome", "Messages and booking history"],
    faqs=[
        ("Can I dispute a background check result?",
         "In the US, the Fair Credit Reporting Act gives you rights to see and dispute consumer reports. Correcting an error at the source strengthens the appeal."),
    ],
    sources=[("Airbnb Help Center", "https://www.airbnb.com/help")],
    related=["booking-com", "uber", "paypal"],
)

add(
    id="uber", cat="gig", name="Uber",
    path="/uber-reinstatement/",
    h1="Uber driver and courier deactivation appeals",
    meta_title="Uber Driver Deactivated? Appeal & Reinstatement Help",
    meta_desc="Uber driver or Uber Eats courier account deactivated? We help you use Uber's review route, dispute background check errors and document your case.",
    lede="Deactivation takes away income overnight. Uber offers a review route for many deactivations, and some cities and states now give app-based drivers specific deactivation rights. We help you use both.",
    accounts="Uber rideshare drivers and Uber Eats couriers.",
    triggers=[
        "Background or motor vehicle record check results",
        "Identity check failures (Real-Time ID Check)",
        "Fraud flags on trips or deliveries",
        "Safety reports and low ratings",
    ],
    notices=[("Your account has been deactivated", "Access is removed. The app or email usually describes whether a review is available.")],
    route=[
        "Request the reason and any review option through the app or Greenlight support.",
        "For background checks, obtain the report and dispute errors with the screening company.",
        "Check local deactivation rights where you drive.",
    ],
    evidence=["Screening report and dispute outcome", "Trip records and screenshots", "Dashcam footage where relevant"],
    faqs=[
        ("Do local laws affect Uber deactivations?",
         "Some do. Jurisdictions including Seattle and Minnesota have introduced deactivation protections for app-based drivers. Check the rules where you drive and consider local legal aid or driver organizations."),
    ],
    sources=[("Uber Help", "https://help.uber.com/")],
    related=["lyft", "doordash", "instacart"],
)

add(
    id="lyft", cat="gig", name="Lyft",
    path="/lyft-reinstatement/",
    h1="Lyft driver deactivation appeals",
    meta_title="Lyft Driver Deactivated? Appeal & Reinstatement Help",
    meta_desc="Lyft driver account deactivated after a background check, safety report or rating issue? We help you prepare a documented appeal through Lyft support.",
    lede="Lyft deactivations commonly follow screening results or safety reports. The strongest appeals correct factual errors at the source and present trip evidence clearly.",
    accounts="Lyft rideshare drivers.",
    triggers=["Background check results", "Safety reports", "Document expiry or vehicle issues"],
    notices=[("Your account has been deactivated", "Driving is blocked. Lyft support is the route to request review.")],
    route=[
        "Obtain the reason in writing.",
        "Correct screening errors with the screening company.",
        "Submit a documented appeal through Lyft support.",
    ],
    evidence=["Screening report", "Trip history", "Current license, insurance and inspection documents"],
    faqs=[("Will expired documents cause a deactivation?", "They cause a pause. Uploading valid documents usually resolves it without an appeal.")],
    sources=[("Lyft Help", "https://help.lyft.com/")],
    related=["uber", "doordash", "instacart"],
)

add(
    id="doordash", cat="gig", name="DoorDash",
    path="/doordash-reinstatement/",
    h1="DoorDash Dasher deactivation appeals",
    meta_title="DoorDash Dasher Deactivated? Appeal & Reinstatement Help",
    meta_desc="Dasher account deactivated for ratings, completion, fraud flags or background check? We help you prepare an appeal under DoorDash's deactivation policy.",
    lede="DoorDash publishes a Dasher deactivation policy and an appeal process. We map your notice to that policy and help you present the facts.",
    accounts="Dashers in the United States.",
    triggers=["Customer ratings and completion rates", "Suspected fraud on orders", "Background check results", "Safety reports"],
    notices=[("Your Dasher account has been deactivated", "The notice explains whether you can appeal and how.")],
    route=[
        "Read the deactivation policy against your notice.",
        "Gather delivery records, photos and messages.",
        "Submit the appeal through DoorDash's official form.",
    ],
    evidence=["Delivery photos and timestamps", "Chat logs with customers and support", "Screening report"],
    faqs=[("Can I appeal a fraud flag?", "Yes, where you have delivery evidence. Photos and timestamps are central.")],
    sources=[("DoorDash Dasher support", "https://help.doordash.com/dashers/")],
    related=["uber", "instacart", "lyft"],
)

add(
    id="instacart", cat="gig", name="Instacart",
    path="/instacart-reinstatement/",
    h1="Instacart shopper deactivation appeals",
    meta_title="Instacart Shopper Deactivated? Appeal & Reinstatement Help",
    meta_desc="Instacart shopper account deactivated? We help you understand the reason, gather order evidence and prepare a factual appeal through Instacart support.",
    lede="Shopper deactivations tend to follow ratings, order issues or fraud flags. We help you rebuild the timeline from your own records.",
    accounts="Instacart full-service and in-store shoppers.",
    triggers=["Customer ratings and reports", "Order accuracy or missing item disputes", "Background check results"],
    notices=[("Your account has been deactivated", "Batches are no longer offered. Support can confirm appeal options.")],
    route=["Request the reason in writing.", "Collect order records and photos.", "Submit the appeal through Instacart shopper support."],
    evidence=["Order and delivery photos", "Customer chat logs", "Screening report"],
    faqs=[("Are ratings alone enough for deactivation?", "They can be a factor. Context about specific orders strengthens an appeal.")],
    sources=[("Instacart shopper help", "https://shoppers.instacart.com/help")],
    related=["doordash", "uber", "lyft"],
)

add(
    id="upwork", cat="gig", name="Upwork",
    path="/upwork-reinstatement/",
    h1="Upwork account suspension appeals",
    meta_title="Upwork Account Suspended? Appeal & Held Earnings Help",
    meta_desc="Upwork freelancer or client account suspended, or earnings held? We help you prepare Upwork's appeal and identity verification with clear evidence.",
    lede="Upwork suspends accounts for identity doubts, off-platform payments, duplicate accounts and Terms of Service issues. Your earnings and reputation sit in that one profile.",
    accounts="Freelancers, agencies and clients.",
    triggers=[
        "Identity verification failures",
        "Taking payment or communication off platform",
        "Multiple accounts",
        "Misrepresentation in profiles or proposals",
    ],
    notices=[("Your account has been suspended", "Work and withdrawals may be paused. Upwork describes the appeal route.")],
    route=[
        "Complete identity verification if requested.",
        "Follow Upwork's published appeal process with a precise, honest explanation.",
    ],
    evidence=["Government ID and video verification", "Contract and message history", "Portfolio ownership proof"],
    funds="Earnings may be held while a suspension is reviewed. Upwork's terms describe how pending and available funds are handled.",
    faqs=[("How do I appeal an Upwork suspension?", "Upwork publishes an appeal process in its help center. We prepare the content and documents; you submit through your account.")],
    sources=[("Upwork: appealing an account suspension", "https://support.upwork.com/hc/en-us/articles/17989816008339--Appealing-an-account-suspension")],
    related=["fiverr", "payoneer", "linkedin"],
)

add(
    id="fiverr", cat="gig", name="Fiverr",
    path="/fiverr-reinstatement/",
    h1="Fiverr account restriction and reinstatement",
    meta_title="Fiverr Account Restricted or Disabled? Reinstatement Help",
    meta_desc="Fiverr seller account restricted, warned or disabled with earnings pending? We review Terms of Service findings and help you prepare the appeal.",
    lede="Fiverr enforces through warnings, restrictions and account closures. Sellers risk losing levels, reviews and earnings, so the response needs to be precise.",
    accounts="Fiverr sellers and buyers.",
    triggers=["Off-platform communication or payment", "Intellectual property complaints", "Multiple accounts", "Review manipulation"],
    notices=[("Your account has been restricted", "Selling may be paused. Trust and Safety explains the reason.")],
    route=["Read the Terms of Service section cited.", "Reply to Trust and Safety in writing with evidence."],
    evidence=["Order and message history", "Original work files", "ID verification"],
    funds="Earnings may be held on restricted or disabled accounts under Fiverr's terms.",
    faqs=[("Does sharing an email address get you banned?", "Sharing contact details outside permitted cases breaches Fiverr's terms and is a common trigger.")],
    sources=[("Fiverr Help", "https://help.fiverr.com/")],
    related=["upwork", "payoneer", "paypal"],
)

# ---------------------------------------------------------------- Email and core accounts
add(
    id="yahoo-mail", legacy=True, cat="email", name="Yahoo Mail",
    path="/yahoo-mail-reinstatement/",
    h1="Yahoo Mail account recovery and reinstatement",
    meta_title="Yahoo Mail Account Locked or Disabled? Recovery Help",
    meta_desc="Yahoo Mail account locked, disabled or deleted for inactivity? We help you use Yahoo's recovery and support routes and secure the account afterward.",
    lede="Yahoo accounts are locked for security reasons, disabled for terms violations or removed after long inactivity. The route back depends on which applies.",
    accounts="Yahoo Mail and Yahoo accounts.",
    triggers=["Suspicious sign-in activity", "Terms of Service violations such as spam", "Long-term inactivity"],
    notices=[
        ("Your account is locked", "Usually a security measure. Recovery steps can unlock it."),
        ("Your account has been deactivated", "Contact Yahoo support through official help routes."),
    ],
    route=["Use Yahoo's Sign-in Helper.", "Contact Yahoo support through official help pages.", "Secure recovery details once back in."],
    evidence=["Recovery email and phone", "Account creation details", "Recent sign-in locations"],
    faqs=[("Can a deleted Yahoo account be recovered?", "Once an account is permanently deleted, recovery may not be possible. Act as soon as you see a lock or warning.")],
    sources=[("Yahoo Help", "https://help.yahoo.com/")],
    related=["gmail", "coinbase", "facebook"],
)

add(
    id="gmail", cat="email", name="Gmail and Google Account",
    path="/gmail-reinstatement/",
    h1="Disabled Google Account and Gmail recovery",
    meta_title="Google Account Disabled? Gmail Recovery & Appeal Help",
    meta_desc="Google Account or Gmail disabled for a policy reason or locked after a security event? We help you use Google's restore and appeal routes correctly.",
    lede="A disabled Google Account locks Gmail, Drive, Photos, YouTube and every service that uses Sign in with Google. We help you file the restore request accurately and prepare the rest of your digital life for either outcome.",
    accounts="Personal Google Accounts and Workspace users (Workspace admins control their own users).",
    triggers=[
        "Policy violations detected in content or activity",
        "Suspicious sign-in or payment activity",
        "Age requirements",
    ],
    notices=[("Your account has been disabled", "Signing in shows the option to try to restore the account.")],
    route=[
        "Sign in and follow the restore option.",
        "Provide a clear, factual request.",
        "For Workspace accounts, contact your administrator first.",
    ],
    evidence=["Recovery email and phone", "Context for the content cited"],
    faqs=[("Can I download my data if the account stays disabled?", "In some cases Google allows data download for disabled accounts. Check the options shown when you sign in.")],
    sources=[("Google Account Help", "https://support.google.com/accounts/")],
    related=["yahoo-mail", "youtube", "google-ads"],
)

PLATFORMS = P
BY_ID = {p["id"]: p for p in P}
