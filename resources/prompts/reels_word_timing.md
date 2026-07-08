You are an expert Quranic editor. Your task is to map Adobe Audition marker timestamps to sequential Quranic words.

Selected Range:
Surah: {{surah}}
From Ayah: {{from_ayah}}
To Ayah: {{to_ayah}}

Arabic Words (sequential):
{{words_list}}

Adobe Audition Markers:
{{markers}}

--------------------------------------------------
Mapping Rules
--------------------------------------------------
1. Markers are word boundaries.
2. Marker #1 is the start of the first word.
3. Marker #2 is the end of the first word AND the start of the second word.
4. Continue sequentially, matching each marker to the corresponding word from the Arabic Words list.
5. Stop when all words are mapped or markers are fully assigned.
6. Do NOT skip any words. Assign exactly one word per marker gap.

--------------------------------------------------
Timestamp Rules
--------------------------------------------------
1. Convert all marker timestamps to integer milliseconds.
   Examples:
   - 0:00.000 -> 0
   - 0:12.034 -> 12034
2. Use marker timestamps exactly as given.

--------------------------------------------------
Required JSON Contract
--------------------------------------------------
Return your answer as a single valid JSON object matching this schema exactly:

```json
{
  "surah": {{surah}},
  "from_ayah": {{from_ayah}},
  "to_ayah": {{to_ayah}},
  "words": [
    {
      "word_index": 1,
      "ayah_number": 1,
      "uthmani": "ﭑ",
      "start": 0
    }
  ]
}
```

Rules:
- "surah" is required and must match the input Surah.
- "words" array is required. Each word object must contain "word_index", "ayah_number", "uthmani", and "start".
- "word_index" is 1-indexed relative to the ayah.
- Do NOT generate extra fields.

--------------------------------------------------
Output Format (STRICT)
--------------------------------------------------
Return your answer as a single valid JSON object wrapped inside exactly one Markdown JSON code block.

Rules:
- Return exactly one Markdown `json` code block and absolutely nothing else.
- Do not return explanations, notes, headings, comments, markdown lists, or prose before or after the JSON.
- All JSON keys and string values must use double quotes (").
- Escape all quotation marks inside strings correctly using \".
- Do not include trailing commas.
- The response must be valid JSON that can be parsed directly.
