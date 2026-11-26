import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useT } from '@/lib/i18n';

interface Props {
    units: { state: string; label: string; count: number }[];
    texts: { source: string; label: string; count: number }[];
    legalReview: { key: string; note: string }[];
    provisionalSettings: { key: string; label: string }[];
    mailProblems: number;
}

/** Dashboard home: counts of what waits for action. The screens behind them come in their phases. */
export default function Overview({ units, texts, legalReview, provisionalSettings, mailProblems }: Props) {
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
                        <CardTitle>{t('overview.texts')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="flex flex-col gap-2">
                            {texts.map((s) => (
                                <div key={s.source} className="flex items-baseline justify-between gap-3 border-b pb-2 last:border-b-0 last:pb-0">
                                    <dt className="text-sm text-muted-foreground">{s.label}</dt>
                                    <dd className="font-mono text-lg tabular-nums">{s.count}</dd>
                                </div>
                            ))}
                        </dl>
                    </CardContent>
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

            <Card>
                <CardHeader>
                    <CardTitle>{t('overview.legal_review')}</CardTitle>
                </CardHeader>
                <CardContent>
                    {legalReview.length === 0 ? (
                        <p className="text-sm text-muted-foreground">{t('overview.none')}</p>
                    ) : (
                        <ul className="flex flex-col divide-y">
                            {legalReview.map((text) => (
                                <li key={text.key} className="flex flex-col gap-1 py-2 sm:flex-row sm:items-baseline sm:gap-4">
                                    <Badge variant="warning" className="w-fit font-mono" dir="ltr">
                                        {text.key}
                                    </Badge>
                                    <span className="text-sm text-muted-foreground" dir="ltr">
                                        {text.note}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
        </DashboardLayout>
    );
}
