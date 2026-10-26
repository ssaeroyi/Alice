# ALICE

**Gamified English Learning Platform**

## Overview

Alice is a web app for learning English through reading. Students pick a story and read
it. They answer a short quiz after. The system tracks their reading time and scores.

Every story has a reading level. Alice works it out with the Flesch Reading Ease and
Flesch-Kincaid formulas. 

Reading earns them badges and keys. They spend the keys in the shop and in the journal. The journal contains mini game actions to complete a story.

New students take a short placement check first. It sets their current reading level.

Teachers build classes and hand out assignments. They can write their own stories too.

## Stacks

### Backend

| | Version |
|---|---|
| PHP | 8.3+ |
| Laravel | 13.25.0 |
| MariaDB / MySQL | 8+ |

### Frontend build

| | Version |
|---|---|
| Vite | 8.3.4 |
| Tailwind CSS | 4.3.3 |
| @tailwindcss/vite | 4.3.3 |
| laravel-vite-plugin | 3.2.0 |

### Testing

| | Version |
|---|---|
| Pest | 5.1.1 |
| Mockery | 1.6.12 |
| Faker | 1.24.1 |
| Playwright | 1.64.1 |

### Python (story narration)

Only the machine that records the voices needs this. The site runs without it.

| | Version |
|---|---|
| Python | 3.13 |
| Kokoro (TTS) | 0.7.16 |
| PyTorch | 2.13.0+cu126 |
| transformers | 5.15.1 |
| spaCy | 3.8.15 |
| NLTK | 3.10.3 |
| misaki | 0.7.4 |
| soundfile | 0.14.0 |

### External services

| Service | Used for |
|---|---|
| Open Library | Book covers |
| Wikipedia | Book covers and story sources |

## JavaScript Libraries

| Library | Version | Used for |
|---|---|---|
| Chart.js | 4.5.1 | Dashboard charts |
| Harper.js | 2.10.0 | Grammar checking. Runs as WASM in `public/vendor/harper`. |
| Vosk Browser | 0.0.8 | Offline speech recognition for the say-it games |
| anime.js | 4.5.0 | Quiz and page animations |
| Flatpickr | 4.6.13 | Date range pickers |
