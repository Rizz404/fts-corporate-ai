# Evaluasi asisten 2026-10-05

Corpus sintetis (docs/evals), bukan data perusahaan. Penilaian otomatis: sumber yang dikutip, fakta kunci, fallback, dan efek domain. Kualitas bahasa tetap perlu dibaca manusia.

| Item | Nilai |
|---|---|
| Provider / model | local_llm / `qwen3.6-35b-a3b-uncensored-hauhaucs-aggressive` |
| Prompt | corporate-v2 (tools: on, reasoning_effort: none) |
| Lulus | 90/90 |
| Akses terlarang semua ditolak | ya |
| Tiket tanpa klik konfirmasi | 0 |
| Efek ganda saat retry | 0 |
| Latensi p50 / p95 | 3.44 s / 6.95 s |
| Total token | 162115 |

| Bahasa | Lulus | Target |
|---|---|---|
| id | 30/30 | 27/30 ✔ |
| en | 30/30 | 27/30 ✔ |
| ja | 30/30 | 27/30 ✔ |

| Kategori | Lulus |
|---|---|
| sop | 45/45 |
| no_answer | 15/15 |
| forbidden | 15/15 |
| ticket_status | 9/9 |
| draft | 6/6 |

## Skenario gagal

Tidak ada.
