"""
Builds core/resources/fonts/Alexandria-{Regular,Bold}.ttf for the exit report PDF (mPDF needs static TrueType).

Source: Alexandria[wght].ttf from https://github.com/google/fonts (ofl/alexandria), SIL Open Font License
(core/resources/fonts/OFL-Alexandria.txt). Steps: cut static instances at weight 400 and 700, then remove the GDEF
mark glyph sets, which mPDF does not support ("contains MarkGlyphSets - Not tested yet"), and the lookup flag
that refers to them. Run in a throwaway virtual environment with fonttools; never inside the project:
    uv venv <scratch>/fontvenv && uv pip install --python <scratch>/fontvenv/Scripts/python.exe fonttools
    <scratch>/fontvenv/Scripts/python.exe scripts/fixtures/make-pdf-fonts.py <path to Alexandria[wght].ttf>
"""

import sys
from pathlib import Path

from fontTools.ttLib import TTFont
from fontTools.varLib.instancer import instantiateVariableFont

OUT = Path(__file__).resolve().parents[2] / "core" / "resources" / "fonts"
USE_MARK_FILTERING_SET = 0x0010


def strip_mark_sets(font: TTFont) -> None:
    if "GDEF" in font:
        gdef = font["GDEF"].table
        gdef.MarkGlyphSetsDef = None
        if gdef.Version > 0x00010000 and getattr(gdef, "VarStore", None) is None:
            gdef.Version = 0x00010000
    for tag in ("GSUB", "GPOS"):
        if tag in font and font[tag].table.LookupList:
            for lookup in font[tag].table.LookupList.Lookup:
                if lookup.LookupFlag & USE_MARK_FILTERING_SET:
                    lookup.LookupFlag &= ~USE_MARK_FILTERING_SET
                    lookup.MarkFilteringSet = None


def main(source: str) -> None:
    for weight, name in ((400, "Regular"), (700, "Bold")):
        font = instantiateVariableFont(TTFont(source), {"wght": weight}, static=True)
        strip_mark_sets(font)
        font.save(OUT / f"Alexandria-{name}.ttf")


if __name__ == "__main__":
    main(sys.argv[1])
