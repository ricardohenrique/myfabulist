import { Head, Link } from '@inertiajs/react';
import { LegalLayout } from '@/components/legal/legal-layout';
import { privacy } from '@/routes';

export default function Terms() {
    return (
        <LegalLayout
            description="The rules that apply when you create an account or use Purplelist."
            eyebrow="Legal"
            title="Terms of Service"
        >
            <Head title="Terms of Service" />

            <section>
                <h2>1. Provider and scope</h2>
                <p>
                    These Terms govern the use of the Purplelist website and task-management service. By creating
                    an account, you enter into an agreement with the operator of Purplelist.
                </p>
                <div className="legal-callout" role="note">
                    <strong>Provider details required before publication</strong>
                    <p>
                        Add the operator's full legal name, legal form (if applicable), postal address, contact
                        email, representative, register and registration number, and VAT ID where applicable.
                    </p>
                </div>
            </section>

            <section>
                <h2>2. The service</h2>
                <p>
                    Purplelist lets you create and organize folders, lists, tasks, notes, subtasks, and comments,
                    and share eligible lists with other registered users. Features may evolve over time, but we
                    will not materially reduce an agreed paid service without providing the notice or remedy
                    required by law.
                </p>
            </section>

            <section>
                <h2>3. Your account</h2>
                <p>
                    You must provide accurate registration information, keep your login credentials confidential,
                    and notify us promptly if you believe your account has been compromised. You are responsible
                    for activity performed through your account unless you are not legally responsible for it.
                </p>
            </section>

            <section>
                <h2>4. Your content and shared lists</h2>
                <p>
                    You retain your rights in content you add to Purplelist. You grant us the limited rights needed
                    to host, process, back up, transmit, and display that content solely to operate and secure the
                    service.
                </p>
                <p>
                    Content in a shared list is visible to accepted members, who can modify the shared list's task
                    content. Only share content with people you trust. Each member controls their own folder
                    placement and starred state; those choices are not shared.
                </p>
            </section>

            <section>
                <h2>5. Acceptable use</h2>
                <p>You must not:</p>
                <ul>
                    <li>use Purplelist unlawfully or infringe another person's rights;</li>
                    <li>upload malicious code or interfere with the service or another user's account;</li>
                    <li>attempt to bypass access controls, rate limits, or security measures;</li>
                    <li>use automated means that place an unreasonable load on the service; or</li>
                    <li>share content you do not have the right to use or disclose.</li>
                </ul>
            </section>

            <section>
                <h2>6. Availability and changes</h2>
                <p>
                    We aim to keep Purplelist reliable but cannot promise uninterrupted or error-free availability.
                    Maintenance, security incidents, or circumstances outside our reasonable control may interrupt
                    access. We may change the service to improve it, maintain security, or comply with law.
                </p>
            </section>

            <section>
                <h2>7. Suspension and ending the agreement</h2>
                <p>
                    You may stop using the service at any time and may request account deletion through the contact
                    details above. We may restrict or suspend access where reasonably necessary to address a
                    security risk, unlawful use, or a material breach of these Terms. Where appropriate, we will
                    give notice and an opportunity to remedy the issue. Statutory termination rights remain
                    unaffected.
                </p>
            </section>

            <section>
                <h2>8. Liability</h2>
                <p>
                    We are liable without limitation for intent and gross negligence, for injury to life, body, or
                    health, under the German Product Liability Act, and where we have given an express guarantee.
                    For slight negligence, we are liable only for breach of an essential contractual obligation and
                    only for damage that was typical and foreseeable when the agreement was made. Mandatory
                    statutory liability remains unaffected.
                </p>
            </section>

            <section>
                <h2>9. Governing law</h2>
                <p>
                    German law applies, excluding the UN Convention on Contracts for the International Sale of
                    Goods. If you are a consumer, this choice does not deprive you of mandatory protections granted
                    by the law of the country where you habitually reside. Statutory rules on jurisdiction apply.
                </p>
            </section>

            <section>
                <h2>10. Consumer dispute resolution</h2>
                <div className="legal-callout" role="note">
                    <strong>Operator decision required before publication</strong>
                    <p>
                        State whether the operator is willing or obliged to participate in dispute-resolution
                        proceedings before a German consumer conciliation body and identify that body if applicable.
                    </p>
                </div>
            </section>

            <section>
                <h2>11. Privacy and changes to these Terms</h2>
                <p>
                    Our <Link href={privacy()}>Privacy Policy</Link> explains how we process personal data. We may
                    update these Terms for valid reasons, including changes to the service or law. If a change
                    materially affects your rights, we will provide reasonable advance notice where required.
                </p>
            </section>
        </LegalLayout>
    );
}
