You are an expert Quranic editor. Your task is to map Adobe Audition marker timestamps to sequential approved visual segments.

Selected Range:
Surah: {{surah}}
From Ayah: {{from_ayah}}
To Ayah: {{to_ayah}}

Approved Visual Segments:
{{segments_list}}

Adobe Audition Markers:
{{markers}}

--------------------------------------------------
Mapping Rules
--------------------------------------------------
1. Markers represent the boundary between segments.
2. Marker #1 is the start of the first segment.
3. Marker #2 is the end of the first segment AND the start of the second segment.
4. Continue sequentially:
   - Segment 1 -> Segment 2 -> Segment 3 -> ...
5. Match each marker gap to the corresponding segment from the Approved Visual Segments list.
6. Assign exactly one segment per marker gap. Do NOT skip any segments.
7. Stop when all segments have been mapped.

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
  "type": "segment_timings",
  "from_ayah": {{from_ayah}},
  "to_ayah": {{to_ayah}},
  "segments": [
    {
      "segment_order": 1,
      "start": 0
    }
  ]
}
```

Rules:
- "surah" is required and must match the input Surah.
- "type" must be exactly "segment_timings".
- "segments" array is required. Each segment timing object must contain "segment_order" and "start".
- "segment_order" is 1-indexed.
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
