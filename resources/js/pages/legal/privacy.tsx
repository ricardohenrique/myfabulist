import { Head, Link } from '@inertiajs/react';
import { LegalLayout } from '@/components/legal/legal-layout';
import { terms } from '@/routes';

export default function Privacy() {
    return (
        <LegalLayout
            description="How Purplelist handles personal data when you visit the website, create an account, and use the service."
            eyebrow="Legal"
            title="Privacy Policy"
        >
            <Head title="Privacy Policy" />

            <section>
                <h2>1. Who is responsible</h2>
                <p>
                    The operator of Purplelist is the controller responsible for processing your personal data
                    within the meaning of the General Data Protection Regulation (GDPR).
                </p>
                <div className="legal-callout" role="note">
                    <strong>Operator details required before publication</strong>
                    <p>
                        Add the operator's full legal name, legal form (if applicable), postal address, contact
                        email, and the data protection officer's details if one has been appointed.
                    </p>
                </div>
            </section>

            <section>
                <h2>2. Data we process</h2>
                <p>Depending on how you use Purplelist, we process:</p>
                <ul>
                    <li><strong>Account data:</strong> your name, email address, password hash (if you set a password), Google account identifier and profile image (if you use Google sign-in), and account settings.</li>
                    <li><strong>Content:</strong> folders, lists, tasks, notes, due dates, subtasks, comments, ordering, completion, and starred status.</li>
                    <li><strong>Collaboration data:</strong> list memberships, invitations, and the identity of comment authors and collaborators.</li>
                    <li><strong>Technical data:</strong> IP address, request time, browser and device information, log data, and session or API-token metadata needed to operate and secure the service.</li>
                    <li><strong>Optional data:</strong> a profile photo and workspace appearance choices if you choose to provide them.</li>
                </ul>
                <p>
                    Please avoid putting sensitive personal data in task titles, notes, or comments unless it is
                    genuinely necessary for your own use.
                </p>
            </section>

            <section>
                <h2>3. Purposes and legal bases</h2>
                <ul>
                    <li>
                        We process account data and content to create your account, provide Purplelist, save and
                        synchronize your work, and enable collaboration. The legal basis is Article 6(1)(b) GDPR
                        (performance of the contract).
                    </li>
                    <li>
                        We process technical and security data to maintain reliable operation, prevent abuse,
                        investigate errors, and protect accounts. The legal basis is Article 6(1)(f) GDPR. Our
                        legitimate interests are the security, stability, and improvement of the service.
                    </li>
                    <li>
                        Where processing is necessary to comply with a legal obligation, the legal basis is
                        Article 6(1)(c) GDPR.
                    </li>
                </ul>
            </section>

            <section>
                <h2>4. Google sign-in</h2>
                <p>
                    If you choose “Continue with Google,” your browser is redirected to Google for
                    authentication. Google provides Purplelist with your Google account identifier, verified
                    email address, name, and profile image. We use this data to sign you in, create or safely
                    link your Purplelist account, and display your profile image. Google's own processing is
                    governed by its{' '}
                    <a href="https://policies.google.com/privacy" rel="noreferrer" target="_blank">privacy policy</a>.
                    Using Google sign-in is optional; you may use an email and password instead.
                </p>
            </section>

            <section>
                <h2>5. Cookies and local storage</h2>
                <p>
                    Purplelist uses strictly necessary first-party cookies for login sessions, CSRF protection,
                    and, when selected, the “keep me signed in” function. These technologies are required to
                    provide the service securely. The current application does not use advertising or analytics
                    cookies.
                </p>
            </section>

            <section>
                <h2>6. Sharing and recipients</h2>
                <p>
                    When you join a shared list, accepted members can see the list's tasks, notes, due dates,
                    subtasks, comments, completion state, and collaborators. Comment attribution is visible to
                    those members. A list owner can see members' email addresses for the purpose of managing the
                    list. Your personal folders, list placement, and starred choices remain private to you.
                </p>
                <p>
                    We may also disclose data to hosting, infrastructure, storage, and support providers acting
                    on our instructions, or to public authorities where the law requires it.
                </p>
                <div className="legal-callout" role="note">
                    <strong>Processor details required before publication</strong>
                    <p>
                        Add the production hosting and infrastructure providers, their processing locations, and
                        any safeguards used for transfers outside the EEA.
                    </p>
                </div>
            </section>

            <section>
                <h2>7. Retention</h2>
                <p>
                    We keep account data and active content while your account is in use. Deleted tasks and lists
                    may remain recoverable for a limited period. After an account or content is permanently
                    deleted, data may remain in restricted backups until the normal backup cycle completes. We
                    may retain limited records longer where required by law or necessary to establish, exercise,
                    or defend legal claims.
                </p>
                <div className="legal-callout" role="note">
                    <strong>Retention periods required before publication</strong>
                    <p>Add the actual soft-delete, log, inactive-account, and backup retention periods.</p>
                </div>
            </section>

            <section>
                <h2>8. Your rights</h2>
                <p>
                    Subject to the legal conditions, you may request access, correction, deletion, restriction,
                    or portability of your personal data. You may object to processing based on legitimate
                    interests. Where processing relies on consent, you may withdraw it at any time without
                    affecting earlier processing.
                </p>
                <p>
                    You also have the right to lodge a complaint with a data protection supervisory authority,
                    particularly in the EU member state where you live or work or where an alleged infringement
                    occurred. Germany's authorities are listed by the{' '}
                    <a href="https://www.bfdi.bund.de/EN/Service/Anschriften/anschriften_node.html" rel="noreferrer" target="_blank">
                        Federal Commissioner for Data Protection and Freedom of Information
                    </a>.
                </p>
            </section>

            <section>
                <h2>9. Automated decisions and changes</h2>
                <p>
                    Purplelist does not use your data for automated decision-making or profiling that produces
                    legal or similarly significant effects. We may update this policy when the service or legal
                    requirements change. The date at the top shows the latest revision.
                </p>
            </section>

            <section>
                <h2>10. Related terms</h2>
                <p>How you may use Purplelist is explained in our <Link href={terms()}>Terms of Service</Link>.</p>
            </section>
        </LegalLayout>
    );
}
