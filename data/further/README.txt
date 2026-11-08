Further books: books counted as scripture by some churches beyond the 66, read on the site (inc/further.php).

1-meqabyan.json, 2-meqabyan.json, 3-meqabyan.json
  The three books of Meqabyan of the Ethiopian Orthodox Tewahedo canon, in the English translation from Ge'ez
  made by Wikisource contributors:
    https://en.wikisource.org/wiki/Translation:1_Meqabyan
    https://en.wikisource.org/wiki/Translation:2_Meqabyan
    https://en.wikisource.org/wiki/Translation:3_Meqabyan
  Licence: CC BY-SA 4.0 (https://creativecommons.org/licenses/by-sa/4.0/). These files are shared under the
  same licence. The text is as on Wikisource, divided into chapters and verses; nothing is reworded. Where the
  page skips a verse number (2 Meqabyan 16:9 and 21:9), so do these files.
  Built by tools/build_meqabyan.py from copies of the pages kept in tools/.cache/meqabyan/ (not in the
  repository). In November 2026 the Wikisource pages carried a proposal to delete them; the text here is the
  copy taken then.

A book is readable when its file is here and its slug is listed in a prepared section of data/canons.json.

jubilees.json
  Jubilees in R. H. Charles's translation from Ge'ez, The Book of Jubilees or the Little Genesis (London: Adam
  and Charles Black, 1902), public domain. Read by tools/build_jubilees.py from the OCR, with word positions, of
  the scan on the Internet Archive (item bookofjubileesor00char): the translation only, without Charles's notes,
  chapter summaries and margin dates. Where the scan lost a verse number, that verse's words stay with the verse
  before; the script lists each place, and the few spots it mends by hand.

hermas.json
  The Shepherd of Hermas in J. B. Lightfoot's translation (The Apostolic Fathers, 1891), public domain, supplied
  by the site owner; built by tools/build_hermas.py. Five Visions, twelve Mandates and ten Parables, each a
  chapter, with each paragraph's printed reference; repeated references in the copy are numbered on.

apocalypse-of-peter.json
  The Apocalypse of Peter in Andrew Rutherfurd's translation (The Ante-Nicene Fathers, Additional Volume, 1896),
  public domain, supplied by the site owner; built by tools/build_apocalypse_peter.py. Chapter 1 is the Akhmim
  fragment, chapter 2 the passages quoted by the Fathers.
  Since 9 November 2026 chapters 1 to 17 are the Ethiopic text in J. K. Elliott's translation as printed in
  Bart D. Ehrman, Lost Scriptures (Oxford University Press, 2003), used with the owner's permission; built by
  tools/build_apocalypse_peter_ethiopic.py, which puts the Greek after it as chapters 18 and 19.

1-enoch.json
  1 Enoch in Richard Laurence's translation, The Book of Enoch the Prophet (London: Kegan Paul, Trench and Co.,
  1883), public domain; read by tools/build_enoch.py from the OCR with word positions of the Cornell copy on the
  Internet Archive (item cu31924090062823). Laurence's own chapter numbering (1 to 105; he has no chapter XI).
