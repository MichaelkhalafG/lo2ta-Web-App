import { Search, X } from "lucide-react";
import { useEffect, useState } from "react";

import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { useT } from "@/lib/i18n";

export function useQueryTerm(key = "q"): [string, (value: string) => void] {
    const [term, setTerm] = useState<string>(() =>
        typeof window === "undefined" ? "" : (new URLSearchParams(window.location.search).get(key) ?? ""),
    );

    useEffect(() => {
        if (typeof window === "undefined") {
            return;
        }
        const url = new URL(window.location.href);
        if (term.trim() === "") {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, term);
        }
        window.history.replaceState(window.history.state, "", url.toString());
    }, [term, key]);

    return [term, setTerm];
}

export function matchesTerm(term: string, ...haystack: Array<string | null | undefined>): boolean {
    const needle = fold(term);
    if (needle === "") {
        return true;
    }
    return haystack.some((value) => value !== null && value !== undefined && fold(value).includes(needle));
}

function fold(value: string): string {
    return value
        .trim()
        .toLowerCase()
        .replace(/[أإآٱ]/g, "ا")
        .replace(/ة/g, "ه")
        .replace(/ى/g, "ي")
        .replace(/[\u064B-\u0652]/g, "");
}

export function QuickSearch({
    value,
    onChange,
    placeholder,
    count,
    total,
}: {
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
    count: number;
    total: number;
}) {
    const t = useT();

    return (
        <div className="mb-3 flex flex-wrap items-center gap-2">
            <div className="relative w-full sm:w-80">
                <Search className="pointer-events-none absolute start-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    type="search"
                    aria-label={placeholder}
                    placeholder={placeholder}
                    className="ps-8"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                />
            </div>
            {value.trim() !== "" ? (
                <>
                    <span className="text-xs text-muted-foreground">
                        {t("table.quick_search_count", ":count من :total", { count, total })}
                    </span>
                    <Button type="button" variant="ghost" size="sm" className="gap-1" onClick={() => onChange("")}>
                        <X className="h-3.5 w-3.5" />
                        {t("table.clear_filters", "إلغاء التصفية")}
                    </Button>
                </>
            ) : null}
        </div>
    );
}
