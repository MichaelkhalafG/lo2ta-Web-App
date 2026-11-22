"use client";

import { useLocale } from "next-intl";
import { useState, useTransition } from "react";
import { moreUnitsAction } from "@/lib/actions/listing";
import type { UnitCard as Card } from "@/lib/listing";
import { UnitCard, type CardWords } from "./UnitCard";

/**
 * Adds the next page of cards in place (Michael 2026-11-22). The numbered page links stay in the HTML for search
 * engines and for visitors without JavaScript.
 */
export function ShowMore({
  query,
  startPage,
  lastPage,
  words,
  list,
  label,
  loading,
}: {
  query: string;
  startPage: number;
  lastPage: number;
  words: CardWords;
  list: boolean;
  label: string;
  loading: string;
}) {
  const locale = useLocale();
  const [cards, setCards] = useState<Card[]>([]);
  const [page, setPage] = useState(startPage);
  const [last, setLast] = useState(lastPage);
  const [pending, start] = useTransition();

  return (
    <>
      {cards.length > 0 ? (
        <div className={list ? "flex flex-col gap-4" : "grid gap-5 sm:grid-cols-2 xl:grid-cols-3"}>
          {cards.map((unit) => (
            <UnitCard key={unit.code} unit={unit} words={words} list={list} />
          ))}
        </div>
      ) : null}
      {page < last ? (
        <button
          type="button"
          disabled={pending}
          onClick={() =>
            start(async () => {
              const next = await moreUnitsAction(query, page + 1, locale);
              setCards((c) => [...c, ...next.cards]);
              setPage(page + 1);
              setLast(next.lastPage);
            })
          }
          className="inline-flex h-control-lg items-center justify-center self-center rounded-sm border border-line-strong px-8 text-body text-text-1 hover:bg-surface-2 disabled:opacity-60"
        >
          {pending ? loading : label}
        </button>
      ) : null}
    </>
  );
}
