<!--
=====================================================================
🤖 INSTRUKSI UNTUK AGENT / INSTRUCTIONS FOR THE AGENT
=====================================================================
ID: Baca seluruh kode, struktur folder, dan dependensi projek ini, lalu
    isi template di bawah. Salin file ini ke root projek sebagai
    `PROJECT_STORY.md`, lalu buat juga `README.md` dari isinya.

EN: Read the project's code, folder structure, and dependencies, then
    fill in the template below. Copy this file to the project root as
    `PROJECT_STORY.md`, then also generate `README.md` from it.

ATURAN / RULES
1. JANGAN mengarang. Kalau data tidak ada di kode/dokumen, tulis `TODO: tanya owner`.
   Do NOT invent facts, numbers, or users. Unknown → `TODO: ask owner`.
2. Tulis dua lapisan / Write in two layers:
   - 🌱 Awam (non-teknis, 1–2 kalimat, tanpa jargon / plain language, no jargon)
   - 🔧 Pro (detail teknis ringkas / short technical detail)
3. Bahasa Indonesia dan English berdampingan. Nada: santai tapi profesional,
   seperti menjelaskan ke rekan kerja yang pintar tapi belum kenal projeknya.
   Bilingual side by side. Tone: casual yet professional.
4. Hapus komentar HTML ini, bagian opsional yang tidak relevan, dan semua
   placeholder `{{...}}` sebelum selesai.
   Remove this comment, irrelevant optional sections, and every `{{...}}` placeholder when done.
5. Blok `frontmatter` di bawah adalah SUMBER DATA untuk card portfolio.
   Jaga batas karakter. Jangan ubah nama field.
   The `frontmatter` block is the DATA SOURCE for portfolio cards. Keep the
   character limits. Do not rename fields.
6. Satu angka konkret lebih kuat dari tiga kata sifat.
   One concrete number beats three adjectives.
=====================================================================
-->

---
# ======== CARD DATA (dipakai portfolio / used by portfolio) ========
slug: "{{project-slug}}"
status: "{{idea | prototype | in-progress | live | archived}}"
featured: false
year: {{2026}}
role:
  id: "{{Peran Anda, mis. Full-stack Developer}}"
  en: "{{Your role, e.g. Full-stack Developer}}"
title:
  id: "{{Judul singkat, maks 40 karakter}}"
  en: "{{Short title, max 40 chars}}"
tagline:
  id: "{{Satu kalimat manfaat, maks 90 karakter}}"
  en: "{{One-line benefit, max 90 chars}}"
card_summary:
  id: "{{2 kalimat: masalah → hasil, maks 180 karakter}}"
  en: "{{2 sentences: problem → result, max 180 chars}}"
audience:
  id: "{{mis. Mahasiswa, UMKM, Tim HR}}"
  en: "{{e.g. Students, small businesses, HR teams}}"
tags: ["{{web}}", "{{ai}}", "{{mobile}}"]
tech_stack: ["{{Next.js}}", "{{PostgreSQL}}"]
highlight_metric:
  value: "{{mis. 60%}}"
  label_id: "{{lebih cepat dari cara manual}}"
  label_en: "{{faster than the manual way}}"
links:
  live: ""
  repo: ""
  demo_video: ""
  case_study: ""
cover_image: "{{/images/projects/slug-cover.png}}"
---

# {{Nama Projek / Project Name}}

> **ID:** {{Tagline Indonesia}}
> **EN:** {{English tagline}}

---

## 1. 🎯 Ringkasan Cepat / Quick Summary
<!-- Prinsip: First Impression & Cognitive Ease — orang memutuskan "lanjut baca atau tidak" dalam ~5 detik. -->
<!-- Principle: First Impression & Cognitive Ease — people decide in ~5 seconds whether to keep reading. -->

| | 🇮🇩 Indonesia | 🇬🇧 English |
|---|---|---|
| **Apa ini? / What is it?** | {{...}} | {{...}} |
| **Untuk siapa? / For whom?** | {{...}} | {{...}} |
| **Hasil utama / Key result** | {{...}} | {{...}} |

**🌱 Awam:** {{Jelaskan seperti ke teman yang bukan orang IT, 1–2 kalimat.}}
**🌱 Plain:** {{Explain it to a friend outside tech, 1–2 sentences.}}

**🔧 Pro:** {{Satu kalimat teknis: arsitektur / pendekatan / skala.}}
**🔧 Pro:** {{One technical sentence: architecture / approach / scale.}}

---

## 2. 👤 Untuk Siapa / Who Is This For
<!-- Prinsip: Empathy Mapping & Persona — solusi bagus dimulai dari memahami manusianya. -->
<!-- Principle: Empathy Mapping & Persona — good solutions start with understanding the human. -->

**Pengguna utama / Primary user:** {{Persona singkat, mis. "Rina, 24, freelancer yang sering lupa menagih invoice"}}

| Dimensi / Dimension | 🇮🇩 | 🇬🇧 |
|---|---|---|
| **Melihat / Sees** | {{Lingkungan & alat yang mereka pakai}} | {{...}} |
| **Mendengar / Hears** | {{Apa kata orang sekitar}} | {{...}} |
| **Berpikir & merasa / Thinks & feels** | {{Kekhawatiran, harapan}} | {{...}} |
| **Melakukan / Does** | {{Kebiasaan & workaround saat ini}} | {{...}} |

**Pengguna sekunder / Secondary users (opsional):** {{...}}
**Bukan untuk / Not for:** {{Siapa yang sengaja tidak dilayani, supaya fokus.}}

---

## 3. 🔥 Masalah yang Ditemukan / The Problem
<!-- Prinsip: Jobs-to-be-Done + Loss Aversion — orang bergerak karena rasa sakit/kerugian yang nyata. -->
<!-- Principle: Jobs-to-be-Done + Loss Aversion — people act to avoid real pain or loss. -->

**Situasi / Situation:** {{Kapan & di mana masalah muncul?}}
**Rasa sakitnya / The pain:** {{Apa yang hilang: waktu, uang, tenaga, ketenangan?}}
**Cara lama / Old way:** {{Bagaimana orang menanganinya sekarang, dan kenapa itu kurang?}}

**Akar masalah / Root cause (5 Whys singkat):**
1. {{Kenapa?}}
2. {{Kenapa itu terjadi?}}
3. {{Akar yang sebenarnya}}

**🌱 Awam:** {{Satu kalimat: "Masalahnya, ... jadi orang harus ..."}}
**🔧 Pro:** {{Batasan teknis / proses yang jadi bottleneck.}}

**Bukti masalah ini nyata / Evidence:** {{Wawancara, data, pengalaman pribadi, riset. Tulis sumbernya. Jika tidak ada → TODO.}}

---

## 4. 💡 Solusi yang Dibuat / The Solution
<!-- Prinsip: Fogg Behavior Model (B = M·A·P) — turunkan usaha, jaga motivasi, beri pemicu tepat. -->
<!-- Principle: Fogg Behavior Model — lower the effort, keep motivation, give a well-timed prompt. -->

**Ide inti / Core idea:** {{Satu kalimat pendekatan.}}

**Cara kerja (3 langkah maksimal) / How it works (max 3 steps):**
1. {{Pengguna melakukan ...}}
2. {{Sistem melakukan ...}}
3. {{Pengguna mendapatkan ...}}

**Fitur utama / Key features** *(maks 5, tulis manfaat, bukan sekadar nama fitur / max 5, benefits over feature names)*

| Fitur / Feature | Manfaat bagi pengguna / User benefit |
|---|---|
| {{...}} | {{...}} |

**Masalah mana yang selesai? / Which problem does it solve?**
- ✅ {{Masalah 1 → bagaimana terselesaikan}}
- ✅ {{Masalah 2 → ...}}
- ⏳ **Belum terselesaikan / Not yet solved:** {{Jujur soal batasan. Ini membangun kepercayaan.}}

---

## 5. 🧠 Keputusan Desain & Prinsip Psikologi / Design Decisions & Psychology
<!-- Pilih prinsip yang BENAR-BENAR dipakai. Jangan menempel semua. -->
<!-- Pick only principles ACTUALLY applied. Don't paste them all. -->

| Keputusan / Decision | Prinsip / Principle | Kenapa efektif / Why it works |
|---|---|---|
| {{mis. Form dipecah per langkah}} | Cognitive Load / Hick's Law | {{Pilihan lebih sedikit → keputusan lebih cepat}} |
| {{mis. Progress bar}} | Goal-Gradient Effect | {{Makin dekat tujuan, makin semangat}} |
| {{mis. Konfirmasi sebelum hapus}} | Loss Aversion / Error Prevention | {{...}} |
| {{mis. Default yang masuk akal}} | Nudge / Default Effect | {{...}} |
| {{mis. Notifikasi sukses}} | Peak-End Rule / Positive Feedback | {{...}} |

<details>
<summary>📚 Daftar prinsip yang bisa dipilih / Principle cheat sheet</summary>

- **Cognitive Load / Hick's Law** — kurangi pilihan & informasi sekaligus / reduce choices & info at once
- **Fogg Behavior Model** — motivasi + kemampuan + pemicu / motivation + ability + prompt
- **Goal-Gradient & Zeigarnik** — progres terlihat & tugas setengah jadi bikin penasaran / visible progress & unfinished tasks pull attention
- **Loss Aversion** — takut rugi > senang untung / losses loom larger than gains
- **Social Proof** — orang mengikuti orang lain / people follow others
- **Nudge & Default Effect** — pilihan awal mengarahkan perilaku / defaults steer behavior
- **Peak-End Rule** — orang mengingat puncak & akhir pengalaman / people remember the peak and the end
- **Jakob's Law** — pengguna suka pola yang sudah familiar / users prefer familiar patterns
- **Self-Determination Theory** — otonomi, kompetensi, keterhubungan / autonomy, competence, relatedness
- **Aesthetic-Usability Effect** — tampilan rapi dianggap lebih mudah dipakai / pretty feels easier to use
</details>

---

## 6. 🌍 Dampak / Impact
<!-- Prinsip: Concrete Evidence & Social Proof — angka + cerita nyata lebih dipercaya. -->
<!-- Principle: Concrete Evidence & Social Proof — numbers + real stories build trust. -->

| Lapisan / Layer | 🇮🇩 | 🇬🇧 |
|---|---|---|
| **Pengguna langsung / Direct users** | {{Apa yang berubah bagi mereka?}} | {{...}} |
| **Lingkungan sekitar / People around them** | {{Keluarga, tim, komunitas, pelanggan}} | {{...}} |
| **Skala lebih luas / Wider scale** | {{Sosial, ekonomi, lingkungan (jika relevan)}} | {{...}} |

**Metrik / Metrics** *(hanya data asli / real data only)*

| Sebelum / Before | Sesudah / After | Cara ukur / How measured |
|---|---|---|
| {{mis. 30 menit}} | {{mis. 5 menit}} | {{...}} |

**Kutipan / Testimonial (opsional):** "{{...}}" — {{Nama, peran}}

**Dampak yang diharapkan tapi belum terbukti / Expected but unproven:** {{Tandai jelas sebagai harapan.}}

---

## 7. 🧗 Tantangan & Pelajaran / Challenges & Lessons
<!-- Prinsip: Growth Mindset & Pratfall Effect — mengakui kesulitan membuat Anda lebih kredibel. -->
<!-- Principle: Growth Mindset & Pratfall Effect — admitting difficulty makes you more credible. -->

| Tantangan / Challenge | Cara mengatasi / How I handled it | Pelajaran / Lesson |
|---|---|---|
| {{...}} | {{...}} | {{...}} |

---

## 8. 🛠️ Detail Teknis / Technical Details
<!-- Untuk pembaca profesional. Boleh ringkas. / For professional readers. Keep it concise. -->

**Tech stack:** {{...}}
**Arsitektur / Architecture:** {{Diagram, atau 2–3 kalimat}}
**Keputusan teknis penting / Key technical decisions:**
- {{Memilih X karena Y, bukan Z}}

### Menjalankan Lokal / Run Locally

```bash
# 1. Clone
git clone {{repo-url}}
cd {{project-folder}}

# 2. Install
{{npm install}}

# 3. Konfigurasi / Configure
cp .env.example .env

# 4. Jalankan / Run
{{npm run dev}}
```

**Prasyarat / Requirements:** {{Node 20+, dll.}}

---

## 9. 🗺️ Status & Langkah Berikutnya / Status & Next Steps

- **Status saat ini / Current status:** {{...}}
- [ ] {{Langkah berikutnya / Next step}}
- [ ] {{...}}

---

## 10. 🎬 Cerita Singkat untuk Halaman Detail / Short Story for Detail Page
<!-- Prinsip: Narrative Transport — manusia mengingat cerita 22x lebih baik daripada fakta lepas (rule of thumb populer). -->
<!-- Principle: Narrative Transport — stories stick better than isolated facts. -->

**🇮🇩** *(80–120 kata / words)*
{{Awal: tokoh + masalah. Tengah: perjuangan & solusi. Akhir: perubahan nyata.}}

**🇬🇧** *(80–120 words)*
{{Start: character + problem. Middle: struggle & solution. End: real change.}}

---

## 11. 🔗 Tautan & Kredit / Links & Credits

- 🌐 Live: {{url}}
- 💻 Repo: {{url}}
- 🎥 Demo: {{url}}
- 🙏 Kredit / Credits: {{Tim, sumber ikon, dataset, inspirasi}}
- 📄 Lisensi / License: {{MIT / All rights reserved}}

---

<!--
=====================================================================
✅ CHECKLIST KUALITAS UNTUK AGENT / QUALITY CHECKLIST FOR THE AGENT
=====================================================================
[ ] Semua `{{...}}` dan `TODO` sudah diganti atau dilaporkan ke owner
[ ] Tidak ada angka / klaim / testimoni yang dikarang
[ ] Setiap bagian punya versi 🌱 Awam yang bisa dipahami tanpa latar teknis
[ ] Field frontmatter patuh batas karakter
[ ] Bahasa ID & EN sama maknanya (bukan terjemahan kaku, tapi setara)
[ ] Minimal satu keterbatasan ditulis jujur
[ ] Prinsip psikologi hanya yang benar-benar diterapkan
[ ] README.md dibuat dari file ini, lalu komentar instruksi dihapus
=====================================================================
-->
