<?php
require_once __DIR__ . '/core/Cart.php';
require_once __DIR__ . '/core/db.php';

$cart = new Cart();
$pageTitle = 'Terms of Sale';
require __DIR__ . '/views/header.php';
?>

<style>
    .legal-container {
        max-width: 900px;
        margin: 4rem auto;
        background: var(--card-bg);
        border: 1px solid rgba(0,0,0,0.05);
        border-radius: var(--border-radius);
        padding: 3rem;
        box-shadow: var(--box-shadow);
        color: var(--text-color);
        line-height: 1.6;
    }
    .legal-container h1 {
        font-family: 'Inter', sans-serif;
        color: var(--primary-dark);
        margin-bottom: 2rem;
        text-align: center;
    }
    .legal-container h2 {
        font-family: 'Inter', sans-serif;
        color: var(--primary-color);
        margin-top: 2rem;
        margin-bottom: 1rem;
        font-size: 1.2rem;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        padding-bottom: 0.5rem;
    }
    .legal-container p {
        margin-bottom: 1rem;
        color: var(--light-text);
    }
    .legal-container ul {
        margin-left: 2rem;
        margin-bottom: 1rem;
        color: var(--light-text);
    }
    .uppercase-disclaimer {
        text-transform: uppercase;
        font-weight: bold;
        color: var(--text-color);
    }
    strong {
        color: var(--primary-dark);
    }
</style>

<div class="legal-container">
    <h1>Terms of Sale, Disclaimer, and Legal Notice</h1>
    <p style="text-align:center;"><em>Last Updated: September 2026</em></p>

    <h2>1. DEFINITIONS & SCOPE</h2>
    <p>By completing a purchase, submitting payment, or acquiring hardware from <strong>IQA Metal</strong> ("Seller"), you ("Buyer") explicitly agree to these Terms of Sale in full.</p>
    <ul>
        <li><strong>"As-Is" / "Where-Is"</strong>: All items are sold in their present condition, with all faults, known and unknown, without any guarantees of future performance.</li>
        <li><strong>"Equipment"</strong>: Any used computer hardware, components, untested units, or parts sold by Seller.</li>
    </ul>

    <h2>2. ABSOLUTE "ALL SALES FINAL" & NO RETURN POLICY</h2>
    <p>All sales are strictly <strong>FINAL</strong> upon checkout and payment submission. Due to our deeply discounted wholesale and secondary-market business model, the Seller does not offer any warranties or return privileges.</p>
    <p><strong>No returns, no refunds, no store credits, and no exchanges will be granted for any reason.</strong> This includes, but is not limited to, buyer remorse, hardware incompatibility, operating system limitations, or undisclosed cosmetic defects. Refusal of a shipment or undeliverable addresses do not entitle the Buyer to a refund. Any packages returned by the carrier will be considered abandoned.</p>

    <h2>3. WAIVER OF WARRANTIES (UCC COMPLIANT)</h2>
    <p class="uppercase-disclaimer">
        THE EQUIPMENT IS SOLD "AS-IS" AND "WITH ALL FAULTS." SELLER EXPRESSLY DISCLAIMS ALL WARRANTIES, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, AND NON-INFRINGEMENT.<br><br>
        NO ORAL OR WRITTEN INFORMATION, PHOTOGRAPHS, LISTING DESCRIPTIONS, OR CUSTOMER SUPPORT COMMUNICATIONS PROVIDED BY SELLER SHALL CREATE AN EXPRESS WARRANTY OR IN ANY WAY INCREASE THE SCOPE OF THIS AGREEMENT.
    </p>

    <h2>4. HARDWARE CONDITION, TESTING, & BATTERY DISCLAIMER</h2>
    <p>Equipment conditions vary. Descriptions detailing testing results (e.g., Power-On/POST, BIOS access) represent the state of the Equipment at the exact time of testing. Seller does not guarantee long-term operational lifespan, component endurance, or complete functionality beyond the explicitly stated test result.</p>
    <p><strong>Consumable Parts Disclaimer:</strong> All rechargeable batteries (laptop batteries), CMOS batteries, rubber feet, thermal paste, and power adapters are consumable items. They are sold strictly without any capacity, health, or performance guarantees.</p>

    <h2>5. DATA SANITATION & SOFTWARE DISCLAIMER</h2>
    <p>All storage drives are sanitized and wiped prior to sale (e.g., NIST 800-88 standard compliance). Seller holds zero liability for any lost, corrupted, or unrecoverable data.</p>
    <p>Operating Systems (OS) and software licenses, if included or pre-installed, are provided purely as-is without any software support, warranty, or guarantee of authenticity. Buyer is solely responsible for OS activation and compliance with software licensing agreements.</p>

    <h2>6. SHIPPING, RISK OF LOSS, & CARRIER DAMAGE</h2>
    <p>All shipments are <strong>FOB Origin</strong>. Risk of loss and title transfer to the Buyer immediately upon the Seller transferring the Equipment to the shipping carrier (e.g., UPS, FedEx, USPS).</p>
    <p>Seller is not responsible for transit damage, delays, or lost packages. Buyers must file any transit damage or lost package claims directly with the respective shipping carrier.</p>

    <h2>7. PAYMENT DISPUTES & CHARGEBACK PREVENTION</h2>
    <p>By agreeing to these "As-Is" terms, the Buyer affirms that opening an "Item Not as Described" dispute or an unauthorized chargeback with their payment gateway (e.g., Stripe, PayPal, credit card issuer) constitutes a material breach of this contract.</p>
    <p>Seller reserves the right to recover any chargeback fees, administrative costs, debt collection fees, and legal fees incurred as a result of fraudulent or improper disputes.</p>

    <h2>8. LIMITATION OF LIABILITY & INDEMNIFICATION</h2>
    <p>Under no circumstances shall the Seller's total aggregate liability exceed the original purchase price paid by the Buyer for the specific Equipment in question.</p>
    <p>Seller strictly disclaims any liability for indirect, punitive, incidental, special, or consequential damages, including loss of profits, downtime, data loss, or third-party claims, arising out of the sale or use of the Equipment.</p>

    <h2>9. THIRD-PARTY OEM DISCLAIMER</h2>
    <p><strong>IQA Metal</strong> is an independent secondary market reseller. We are NOT affiliated with, sponsored by, endorsed by, or authorized by any original equipment manufacturers (OEMs) such as Dell, HP, Lenovo, Apple, or Microsoft. All trademarks are the property of their respective owners.</p>

    <h2>10. GOVERNING LAW & JURISDICTION</h2>
    <p>These Terms of Sale shall be governed by and construed in accordance with the laws of <strong>the State of California, County of Los Angeles, USA</strong>. Any legal action or proceeding arising under this Agreement shall be brought exclusively in the courts located in <strong>Los Angeles County, California</strong>.</p>
    
    <h2>11. BINDING ARBITRATION & CLASS ACTION WAIVER</h2>
    <p>Any dispute, claim, or controversy arising out of or relating to this Agreement or the breach, termination, enforcement, interpretation, or validity thereof, including the determination of the scope or applicability of this agreement to arbitrate, shall be determined by binding arbitration in <strong>Los Angeles County, California</strong> before one arbitrator. The arbitration shall be administered by the American Arbitration Association (AAA) pursuant to its Commercial Arbitration Rules and Mediation Procedures.</p>
    <p><strong>Class Action Waiver:</strong> The Buyer and Seller agree that any arbitration shall be conducted in their individual capacities only and not as a class action or other representative action. The Buyer expressly waives the right to file a class action or seek relief on a class basis.</p>
    
    <h2>12. SEVERABILITY</h2>
    <p>If any provision of these Terms of Sale is found to be invalid, illegal, or unenforceable by a court of competent jurisdiction, the remaining provisions of these Terms of Sale shall remain in full force and effect. The invalid or unenforceable provision shall be deemed modified so that it is valid and enforceable to the maximum extent permitted by law.</p>

    <h2>13. TAXES, DUTIES, AND IMPORT FEES</h2>
    <p>The Buyer is solely responsible for all applicable sales taxes, value-added taxes (VAT), import duties, and customs fees associated with their purchase. Refusal to pay customs fees or duties upon import will be considered an abandonment of the shipment by the Buyer, and no refund will be issued.</p>
    
    <h2>14. AGE AND CAPACITY TO CONTRACT</h2>
    <p>By placing an order, the Buyer warrants and represents that they are at least eighteen (18) years of age, or the legal age of majority in their jurisdiction of residence, and possess the legal right and capacity to enter into this binding Agreement.</p>

    <h2>15. FORCE MAJEURE</h2>
    <p>Seller shall not be liable for any delay or failure to perform its obligations under these Terms of Sale if such delay or failure is due to causes beyond its reasonable control, including but not limited to acts of God, natural disasters, pandemic, strikes, labor disputes, acts of war or terrorism, civil unrest, or widespread carrier network outages.</p>
</div>

<?php require __DIR__ . '/views/footer.php'; ?>
