import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useT } from '@/lib/i18n';

interface Props {
    units: { state: string; label: string; count: number }[];
    placeholderTexts: number;
    provisionalSettings: { key: string; label: string }[];
    mailProblems: number;
}

/** Dashboard home: counts of what waits for action. The screens behind them come in their phases. */
export default function Overview({ units, placeholderTexts, provisionalSettings, mailProblems }: Props) {
    const t = useT();

    return (
        <DashboardLayout title={t('overview.title')} crumbs={[{ label: t('overview.title') }]}>
            <Card>
                <CardHeader>
                    <CardTitle>{t('overview.units')}</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        {units.map((u) => (
                            <div key={u.state} className="flex flex-col gap-1 border-s ps-3">
                                <dt className="text-xs text-muted-foreground">{u.label}</dt>
                                <dd className="font-mono text-xl tabular-nums">{u.count}</dd>
                            </div>
                        ))}
                    </dl>
                </CardContent>
            </Card>

            <div className="grid gap-6 md:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle>{t('overview.placeholder_texts')}</CardTitle>
                    </CardHeader>
                    <CardContent className="font-mono text-2xl tabular-nums">{placeholderTexts}</CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>{t('overview.mail_problems')}</CardTitle>
                    </CardHeader>
                    <CardContent className="font-mono text-2xl tabular-nums">{mailProblems}</CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>{t('overview.provisional_settings')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {provisionalSettings.length === 0 ? (
                            <p className="text-sm text-muted-foreground">{t('overview.none')}</p>
                        ) : (
                            <ul className="flex flex-wrap gap-1.5">
                                {provisionalSettings.map((s) => (
                                    <li key={s.key}>
                                        <Badge variant="warning" title={s.key}>
                                            {s.label}
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </DashboardLayout>
    );
}
