#!/usr/bin/env python3
"""Build languages/tbe-child-{ms_MY,ar}.po and .mo from languages/tbe-child.pot.

Only visitor-facing strings are translated; admin screens stay in English.
No msgfmt needed: this script writes the binary .mo format itself.
Run tools/make_pot.py first if strings in the PHP files have changed.
"""
import os, re, struct, sys

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
LANG = os.path.join(ROOT, "languages")

# ----------------------------------------------------------------------------------------------
# Bahasa Melayu (standard / Dewan orthography)
MS = {
    "%d+ verses": "%d+ ayat",
    "%d note in this chapter": ["%d nota dalam bab ini"],
    "%d verse": ["%d ayat"],
    "%d chapter": ["%d bab"],
    "Books": "Kitab",
    "Breadcrumb": "Jejak navigasi",
    "By passage": "Mengikut petikan",
    "By section": "Mengikut bahagian",
    "By theme": "Mengikut tema",
    "Chapter %d": "Bab %d",
    "Chapter navigation": "Navigasi bab",
    "Chapters": "Bab",
    "Close all notes": "Tutup semua nota",
    "Commentary": "Ulasan",
    "Commentary on verse %d": "Ulasan bagi ayat %d",
    "Compares manuscripts and variant readings to establish the earliest text that can be recovered.": "Membandingkan manuskrip dan variasi bacaan untuk menentukan teks terawal yang dapat dipulihkan.",
    "Contemplation of the passage, the counterpart of tadabbur.": "Renungan terhadap petikan ini, setara dengan tadabbur.",
    "Continuous text": "Teks bersambung",
    "Examines language, context, authorship and historical background.": "Meneliti bahasa, konteks, kepengarangan dan latar sejarah.",
    "Glossary": "Glosari",
    "Go": "Pergi",
    "Go to a passage or search": "Pergi ke petikan atau cari",
    "Go to a passage or search — e.g. John 1:1-18, mercy": "Pergi ke petikan atau cari — cth. John 1:1-18, mercy",
    "In the commentary": "Dalam ulasan",
    "In the text": "Dalam teks",
    "Index of commentary": "Indeks ulasan",
    "Introduction": "Pengenalan",
    "Jump to letter": "Lompat ke huruf",
    "Language": "Bahasa",
    "Larger text": "Teks lebih besar",
    "Smaller text": "Teks lebih kecil",
    "list of books": "senarai kitab",
    "New Testament": "Perjanjian Baru",
    "Old Testament": "Perjanjian Lama",
    "Next": "Seterusnya",
    "Previous": "Sebelumnya",
    "No commentary has been published yet.": "Belum ada ulasan yang diterbitkan.",
    "No commentary matched.": "Tiada ulasan yang sepadan.",
    "No verses matched.": "Tiada ayat yang sepadan.",
    "Notes": "Nota",
    "Open all notes": "Buka semua nota",
    "Opens the Qur'an on quran.com in a new tab.": "Membuka Al-Quran di quran.com dalam tab baharu.",
    "Page %1$d of %2$d": "Halaman %1$d daripada %2$d",
    "Passage": "Petikan",
    "Passage not found": "Petikan tidak dijumpai",
    "Permalink to this note": "Pautan tetap ke nota ini",
    "Qur'an": "Al-Quran",
    "Qur'an %1$s (%2$s)": "Al-Quran %1$s (%2$s)",
    "Qur'an cross-references": "Rujukan silang Al-Quran",
    "Reading": "Bacaan",
    "Reading tools": "Alat bacaan",
    "Reads the passage in light of the Qur'an and Islamic scholarship (tafsir).": "Membaca petikan ini berdasarkan Al-Quran dan keilmuan Islam (tafsir).",
    "Result pages": "Halaman hasil",
    "Scripture": "Kitab suci",
    "Search": "Cari",
    "Search the text and commentary": "Cari dalam teks dan ulasan",
    'Search words or "a phrase"': 'Cari perkataan atau "satu frasa"',
    "Search: %s": "Carian: %s",
    "Section": "Bahagian",
    "Sections": "Bahagian",
    "Switch between light and dark": "Tukar antara cerah dan gelap",
    "Switch between one verse per line and continuous paragraphs": "Tukar antara satu ayat setiap baris dengan perenggan bersambung",
    "Textual variants": "Variasi teks",
    "Textual variants table": "Jadual variasi teks",
    "That book or chapter does not exist. Try the %s.": "Kitab atau bab itu tidak wujud. Cuba %s.",
    "The Books of the Bible": "Kitab-kitab dalam Bible",
    "The ESV text is not fully loaded yet: the search covers %1$d of %2$d chapters.": "Teks ESV belum dimuatkan sepenuhnya: carian meliputi %1$d daripada %2$d bab.",
    "The glossary is empty for now.": "Glosari masih kosong buat masa ini.",
    "The text of this chapter is not available right now.": "Teks bab ini belum tersedia.",
    "Theme": "Tema",
    "Themes": "Tema",
    "This note cross-references the Qur'an.": "Nota ini membuat rujukan silang kepada Al-Quran.",
    "This note includes a table of manuscript variants.": "Nota ini mengandungi jadual variasi manuskrip.",
    "Type a word or phrase. Put a phrase in quotes. To go straight to a passage, type a reference such as John 3:16.": "Taip perkataan atau frasa. Letakkan frasa dalam tanda petik. Untuk terus ke satu petikan, taip rujukan seperti John 3:16.",
    "Variants": "Variasi",
    "Verse": "Ayat",
    "Verse numbers": "Nombor ayat",
    "Witnesses": "Saksi teks",
    "%d+ verses ": "%d+ ayat",
}
MS_PLURAL = "nplurals=1; plural=0;"

# ----------------------------------------------------------------------------------------------
# Arabic (Modern Standard); six plural forms: zero, one, two, few (3-10), many (11-99), other.
AR = {
    "%d+ verses": "%d+ آية",
    "%d note in this chapter": ["لا ملاحظات في هذا الإصحاح", "ملاحظة واحدة في هذا الإصحاح", "ملاحظتان في هذا الإصحاح", "%d ملاحظات في هذا الإصحاح", "%d ملاحظة في هذا الإصحاح", "%d ملاحظة في هذا الإصحاح"],
    "%d verse": ["لا آيات", "آية واحدة", "آيتان", "%d آيات", "%d آية", "%d آية"],
    "%d chapter": ["لا إصحاحات", "إصحاح واحد", "إصحاحان", "%d إصحاحات", "%d إصحاحًا", "%d إصحاح"],
    "Books": "الأسفار",
    "Breadcrumb": "مسار التنقل",
    "By passage": "حسب المقطع",
    "By section": "حسب القسم",
    "By theme": "حسب الموضوع",
    "Chapter %d": "الإصحاح %d",
    "Chapter navigation": "التنقل بين الإصحاحات",
    "Chapters": "الإصحاحات",
    "Close all notes": "إغلاق كل الملاحظات",
    "Commentary": "التعليق",
    "Commentary on verse %d": "تعليق على الآية %d",
    "Compares manuscripts and variant readings to establish the earliest text that can be recovered.": "يقارن بين المخطوطات والقراءات المختلفة لتحديد أقدم نص يمكن استرداده.",
    "Contemplation of the passage, the counterpart of tadabbur.": "تأمّل في المقطع، وهو نظير التدبّر.",
    "Continuous text": "نص متصل",
    "Examines language, context, authorship and historical background.": "يفحص اللغة والسياق والتأليف والخلفية التاريخية.",
    "Glossary": "المعجم",
    "Go": "اذهب",
    "Go to a passage or search": "اذهب إلى مقطع أو ابحث",
    "Go to a passage or search — e.g. John 1:1-18, mercy": "اذهب إلى مقطع أو ابحث — مثال: John 1:1-18، رحمة",
    "In the commentary": "في التعليقات",
    "In the text": "في النص",
    "Index of commentary": "فهرس التعليقات",
    "Introduction": "مدخل",
    "Jump to letter": "انتقل إلى الحرف",
    "Language": "اللغة",
    "Larger text": "نص أكبر",
    "Smaller text": "نص أصغر",
    "list of books": "قائمة الأسفار",
    "New Testament": "العهد الجديد",
    "Old Testament": "العهد القديم",
    "Next": "التالي",
    "Previous": "السابق",
    "No commentary has been published yet.": "لم يُنشر أي تعليق بعد.",
    "No commentary matched.": "لا توجد تعليقات مطابقة.",
    "No verses matched.": "لا توجد آيات مطابقة.",
    "Notes": "ملاحظات",
    "Open all notes": "فتح كل الملاحظات",
    "Opens the Qur'an on quran.com in a new tab.": "يفتح القرآن في موقع quran.com في تبويب جديد.",
    "Page %1$d of %2$d": "الصفحة %1$d من %2$d",
    "Passage": "المقطع",
    "Passage not found": "المقطع غير موجود",
    "Permalink to this note": "رابط دائم لهذه الملاحظة",
    "Qur'an": "القرآن",
    "Qur'an %1$s (%2$s)": "القرآن %1$s (%2$s)",
    "Qur'an cross-references": "إحالات قرآنية",
    "Reading": "القراءة",
    "Reading tools": "أدوات القراءة",
    "Reads the passage in light of the Qur'an and Islamic scholarship (tafsir).": "يقرأ المقطع في ضوء القرآن والعلم الإسلامي (التفسير).",
    "Result pages": "صفحات النتائج",
    "Scripture": "نص مقدس",
    "Search": "بحث",
    "Search the text and commentary": "ابحث في النص والتعليقات",
    'Search words or "a phrase"': "ابحث عن كلمات أو «عبارة»",
    "Search: %s": "بحث: %s",
    "Section": "القسم",
    "Sections": "الأقسام",
    "Switch between light and dark": "التبديل بين الفاتح والداكن",
    "Switch between one verse per line and continuous paragraphs": "التبديل بين آية في كل سطر وفقرات متصلة",
    "Textual variants": "القراءات النصية المختلفة",
    "Textual variants table": "جدول القراءات النصية المختلفة",
    "That book or chapter does not exist. Try the %s.": "هذا السفر أو الإصحاح غير موجود. جرّب %s.",
    "The Books of the Bible": "أسفار الكتاب المقدس",
    "The ESV text is not fully loaded yet: the search covers %1$d of %2$d chapters.": "نص ESV لم يُحمَّل بالكامل بعد: يشمل البحث %1$d من %2$d إصحاحًا.",
    "The glossary is empty for now.": "المعجم فارغ حاليًا.",
    "The text of this chapter is not available right now.": "نص هذا الإصحاح غير متاح حاليًا.",
    "Theme": "الموضوع",
    "Themes": "المواضيع",
    "This note cross-references the Qur'an.": "تتضمن هذه الملاحظة إحالات إلى القرآن.",
    "This note includes a table of manuscript variants.": "تتضمن هذه الملاحظة جدولًا بقراءات المخطوطات المختلفة.",
    "Type a word or phrase. Put a phrase in quotes. To go straight to a passage, type a reference such as John 3:16.": "اكتب كلمة أو عبارة. ضع العبارة بين علامتي تنصيص. للانتقال مباشرة إلى مقطع، اكتب مرجعًا مثل John 3:16.",
    "Variants": "قراءات مختلفة",
    "Verse": "آية",
    "Verse numbers": "أرقام الآيات",
    "Witnesses": "الشواهد",
}
AR_PLURAL = "nplurals=6; plural=(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5);"


# ----------------------------------------------------------------------------------------------
def unq(s):
    return s.encode().decode("unicode_escape") if False else re.sub(r'\\(.)', lambda m: {"n": "\n", '"': '"', "\\": "\\"}.get(m.group(1), m.group(1)), s)


def q(s):
    return '"' + s.replace("\\", "\\\\").replace('"', '\\"').replace("\n", "\\n") + '"'


def read_pot():
    entries = []  # (msgid, msgid_plural|None)
    cur = None
    for line in open(os.path.join(LANG, "tbe-child.pot"), encoding="utf8"):
        line = line.rstrip("\n")
        m = re.match(r'msgid "(.*)"$', line)
        if m and m.group(1):
            cur = [unq(m.group(1)), None]
            entries.append(cur)
            continue
        m = re.match(r'msgid_plural "(.*)"$', line)
        if m and cur:
            cur[1] = unq(m.group(1))
    return entries


def write_po_and_mo(code, table, plural_rule, nplurals):
    entries = read_pot()
    known = {e[0] for e in entries}
    unknown = [k for k in table if k not in known and k.strip() == k]
    if unknown:
        print("  note: keys not in POT (ignored):", unknown)
    po = ['msgid ""', 'msgstr ""', r'"Project-Id-Version: TBE\n"', r'"Language: %s\n"' % code,
          r'"MIME-Version: 1.0\n"', r'"Content-Type: text/plain; charset=UTF-8\n"', r'"Content-Transfer-Encoding: 8bit\n"',
          r'"Plural-Forms: %s\n"' % plural_rule, ""]
    mo = {"": "Project-Id-Version: TBE\nLanguage: %s\nMIME-Version: 1.0\nContent-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit\nPlural-Forms: %s\n" % (code, plural_rule)}
    done = 0
    for sing, plur in entries:
        tr = table.get(sing)
        po.append("msgid " + q(sing))
        if plur:
            po.append("msgid_plural " + q(plur))
            forms = tr if isinstance(tr, list) else None
            for i in range(nplurals):
                po.append("msgstr[%d] %s" % (i, q(forms[i] if forms and i < len(forms) else (forms[-1] if forms else ""))))
            if forms:
                mo[sing + "\0" + plur] = "\0".join(forms[i] if i < len(forms) else forms[-1] for i in range(nplurals))
                done += 1
        else:
            po.append("msgstr " + q(tr or ""))
            if tr:
                mo[sing] = tr
                done += 1
        po.append("")
    open(os.path.join(LANG, "tbe-child-%s.po" % code), "w", encoding="utf8").write("\n".join(po))
    write_mo(os.path.join(LANG, "tbe-child-%s.mo" % code), mo)
    print("%s: %d of %d strings translated" % (code, done, len(entries)))


def write_mo(path, messages):
    keys = sorted(messages)
    ids, strs, offsets = b"", b"", []
    for k in keys:
        kb, vb = k.encode("utf8"), messages[k].encode("utf8")
        offsets.append((len(ids), len(kb), len(strs), len(vb)))
        ids += kb + b"\0"
        strs += vb + b"\0"
    n = len(keys)
    keystart = 7 * 4 + 16 * n
    valuestart = keystart + len(ids)
    out = struct.pack("Iiiiiii", 0x950412DE, 0, n, 7 * 4, 7 * 4 + n * 8, 0, 0)
    out += b"".join(struct.pack("ii", l1, keystart + o1) for (o1, l1, o2, l2) in offsets)
    out += b"".join(struct.pack("ii", l2, valuestart + o2) for (o1, l1, o2, l2) in offsets)
    open(path, "wb").write(out + ids + strs)


if __name__ == "__main__":
    write_po_and_mo("ms_MY", MS, MS_PLURAL, 1)
    write_po_and_mo("ar", AR, AR_PLURAL, 6)
