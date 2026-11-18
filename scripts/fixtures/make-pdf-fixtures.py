"""
Builds the PDF fixtures for core/tests/Feature/Seller/PdfInspectorTest.php with pypdf, an implementation independent
of ours, so the decryptor is tested against real encryption rather than against itself.

Run it in a throwaway virtual environment, never inside the project (nothing here is a project dependency):
    uv venv <scratch>/pdfvenv
    uv pip install --python <scratch>/pdfvenv pypdf==6.16.1 cryptography
    <scratch>/pdfvenv/Scripts/python scripts/fixtures/make-pdf-fixtures.py

The output is committed; rerun only when a fixture has to change.
"""

from pathlib import Path

from pypdf import PdfWriter
from pypdf.generic import DecodedStreamObject, NameObject, NumberObject

OUT = Path(__file__).resolve().parents[2] / "core" / "tests" / "fixtures" / "uploads" / "pdf"
SCHEMES = {"rc4-40": "RC4-40", "rc4-128": "RC4-128", "aes-128": "AES-128", "aes-256-r5": "AES-256-R5", "aes-256": "AES-256"}


def base() -> PdfWriter:
    writer = PdfWriter()
    writer.add_blank_page(595, 842)
    writer.add_metadata({"/Producer": "lokta-fixture"})
    return writer


def with_object_stream(writer: PdfWriter, body: bytes) -> PdfWriter:
    """A compressed object stream (/N, /First) holding one object, reachable from the catalog."""
    header = b"900 0 "
    stream = DecodedStreamObject()
    stream.set_data(header + body)
    stream.update({
        NameObject("/Type"): NameObject("/ObjStm"),
        NameObject("/N"): NumberObject(1),
        NameObject("/First"): NumberObject(len(header)),
    })
    encoded = stream.flate_encode()
    writer._root_object[NameObject("/LoktaFixture")] = writer._add_object(encoded)
    return writer


CLEAN_BODY = b"<< /Type /LoktaNote /Value 1 >>"
ACTIVE_BODY = b"<< /S /JavaScript /JS (app.alert\\(1\\)) >>"


def save(writer: PdfWriter, name: str) -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    with open(OUT / name, "wb") as handle:
        writer.write(handle)


def main() -> None:
    save(base(), "clean.pdf")
    save(with_object_stream(base(), CLEAN_BODY), "objstm-clean.pdf")
    save(with_object_stream(base(), ACTIVE_BODY), "objstm-js.pdf")

    js = base()
    js.add_js("app.alert('lokta fixture');")
    save(js, "js-openaction.pdf")

    attached = base()
    attached.add_attachment("note.txt", b"embedded file fixture")
    save(attached, "embedded-file.pdf")

    for slug, algorithm in SCHEMES.items():
        for kind, body in (("clean", CLEAN_BODY), ("js", ACTIVE_BODY)):
            writer = with_object_stream(base(), body)
            writer.encrypt(user_password="", owner_password="owner-only-fixture", algorithm=algorithm)
            save(writer, f"enc-{slug}-{kind}.pdf")
        locked = base()
        locked.encrypt(user_password="needs-a-password", owner_password="owner-fixture", algorithm=algorithm)
        save(locked, f"enc-{slug}-password.pdf")


if __name__ == "__main__":
    main()
