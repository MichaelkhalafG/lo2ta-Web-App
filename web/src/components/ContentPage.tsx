import type { ReactNode } from "react";
import { blocks, type Inline } from "@/lib/blocks";

/**
 * A text page (about, how it works, FAQ, buyers, contact, legal): a drawing-number style label, the title, the
 * intro, then the body inside .lk-content (the only place links turn gold on hover).
 */
export function ContentPage({
  label,
  title,
  intro,
  children,
}: {
  label: string;
  title: string;
  intro?: string;
  children: ReactNode;
}) {
  return (
    <article className="px-gutter-mobile pt-12 pb-20 md:px-gutter md:pt-20 md:pb-24">
      <div className="grid gap-10 lg:grid-cols-[minmax(0,4fr)_minmax(0,8fr)] lg:gap-14">
        <header className="flex flex-col gap-5">
          <span className="font-mono text-annotation text-text-3" dir="ltr">
            {label}
          </span>
          <h1 className="text-h1-m md:text-h1">{title}</h1>
          {intro ? <p className="text-lead text-text-2">{intro}</p> : null}
        </header>
        <div className="lk-content flex max-w-[44rem] flex-col gap-10 text-text-2">{children}</div>
      </div>
    </article>
  );
}

/** One text body as blocks (lib/blocks.ts): paragraphs, headings, lists and bold leads; never HTML. */
export function Body({ text, headingLevel = 2 }: { text: string; headingLevel?: 2 | 3 | 4 }) {
  const Heading = headingLevel === 2 ? "h2" : headingLevel === 3 ? "h3" : "h4";

  return (
    <div className="flex flex-col gap-4">
      {blocks(text).map((block, i) => {
        if (block.kind === "heading") {
          return (
            <Heading key={i} className={`${headingLevel === 2 ? "text-h3" : "text-body font-semibold"} pt-2 text-text-1`}>
              {block.text}
            </Heading>
          );
        }
        if (block.kind === "list") {
          const List = block.ordered ? "ol" : "ul";

          return (
            <List key={i} className={`flex flex-col gap-3 ps-6 ${block.ordered ? "list-decimal" : "list-disc"} marker:text-text-3`}>
              {block.items.map((item, j) => (
                <li key={j} className="ps-1">
                  <Lead inline={item} />
                </li>
              ))}
            </List>
          );
        }

        return (
          <p key={i}>
            <Lead inline={block.inline} />
          </p>
        );
      })}
    </div>
  );
}

function Lead({ inline }: { inline: Inline }) {
  return inline.lead === null ? (
    inline.text
  ) : (
    <>
      <strong className="font-semibold text-text-1">{inline.lead}</strong> {inline.text}
    </>
  );
}
