import localFont from "next/font/local";

/*
 * Self-hosted fonts (decided 2026-11-17): the files are committed in src/fonts (SIL OFL, licences beside them),
 * so no build and no visitor ever depends on reaching Google. One instance per script, each limited to its
 * unicode-range, so a page only downloads the files whose characters it uses.
 */

export const alexandriaArabic = localFont({
  src: "../fonts/alexandria-arabic-wght-normal.woff2",
  weight: "100 900",
  variable: "--font-alexandria-arabic",
  display: "swap",
  // No generated Arial fallback face: Arial has Arabic and Latin glyphs, so it would catch characters meant for
  // the next Alexandria file in the stack (one file per script, by unicode-range).
  adjustFontFallback: false,
  declarations: [
    {
      prop: "unicode-range",
      value:
        "U+0600-06FF,U+0750-077F,U+0870-088E,U+0890-0891,U+0897-08E1,U+08E3-08FF,U+200C-200E,U+2010-2011,U+204F,U+2E41,U+FB50-FDFF,U+FE70-FE74,U+FE76-FEFC",
    },
  ],
});

export const alexandriaLatin = localFont({
  src: "../fonts/alexandria-latin-wght-normal.woff2",
  weight: "100 900",
  variable: "--font-alexandria-latin",
  display: "swap",
  // No generated Arial fallback face: Arial has Arabic and Latin glyphs, so it would catch characters meant for
  // the next Alexandria file in the stack (one file per script, by unicode-range).
  adjustFontFallback: false,
  declarations: [
    {
      prop: "unicode-range",
      value:
        "U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD",
    },
  ],
});

export const alexandriaLatinExt = localFont({
  src: "../fonts/alexandria-latin-ext-wght-normal.woff2",
  weight: "100 900",
  variable: "--font-alexandria-latin-ext",
  display: "swap",
  // No generated Arial fallback face: Arial has Arabic and Latin glyphs, so it would catch characters meant for
  // the next Alexandria file in the stack (one file per script, by unicode-range).
  adjustFontFallback: false,
  preload: false,
  declarations: [
    {
      prop: "unicode-range",
      value:
        "U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF",
    },
  ],
});

export const plexMono = localFont({
  src: [
    { path: "../fonts/ibm-plex-mono-latin-400-normal.woff2", weight: "400", style: "normal" },
    { path: "../fonts/ibm-plex-mono-latin-500-normal.woff2", weight: "500", style: "normal" },
  ],
  variable: "--font-plex-mono",
  display: "swap",
  // No generated Arial fallback face: Arial has Arabic and Latin glyphs, so it would catch characters meant for
  // the next Alexandria file in the stack (one file per script, by unicode-range).
  adjustFontFallback: false,
});

/** Class names that set the four font variables on <html>. */
export const fontVariables = [alexandriaArabic, alexandriaLatin, alexandriaLatinExt, plexMono]
  .map((font) => font.variable)
  .join(" ");
