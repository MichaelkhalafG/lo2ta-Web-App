import DashboardLayout from '@/Components/DashboardLayout';
import { useT } from '@/lib/i18n';

interface Props {
    units: { state: string; label: string; count: number }[];
    placeholderTexts: number;
    provisionalSettings: string[];
    mailProblems: number;
}

/** Dashboard home: counts of what waits for action. The screens behind them come in their phases. */
export default function Overview({ units, placeholderTexts, provisionalSettings, mailProblems }: Props) {
    const t = useT();

    return (
        <DashboardLayout title={t('overview.title')}>
            <h1>{t('overview.title')}</h1>

            <section aria-labelledby="units-heading">
                <h2 id="units-heading">{t('overview.units')}</h2>
                <dl>
                    {units.map((u) => (
                        <div key={u.state}>
                            <dt>{u.label}</dt>
                            <dd>{u.count}</dd>
                        </div>
                    ))}
                </dl>
            </section>

            <section aria-labelledby="texts-heading">
                <h2 id="texts-heading">{t('overview.placeholder_texts')}</h2>
                <p>{placeholderTexts}</p>
            </section>

            <section aria-labelledby="settings-heading">
                <h2 id="settings-heading">{t('overview.provisional_settings')}</h2>
                {provisionalSettings.length === 0 ? (
                    <p>{t('overview.none')}</p>
                ) : (
                    <ul dir="ltr">
                        {provisionalSettings.map((key) => (
                            <li key={key}>
                                <code>{key}</code>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section aria-labelledby="mail-heading">
                <h2 id="mail-heading">{t('overview.mail_problems')}</h2>
                <p>{mailProblems}</p>
            </section>
        </DashboardLayout>
    );
}
