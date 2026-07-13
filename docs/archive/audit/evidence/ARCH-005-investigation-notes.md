# ARCH-005 — Investigation notes (no code change made)

## What was found

The finding assumed two PDF engines were both in active use for different documents. On inspection this is not the case:

- **DomPDF** (`barryvdh/laravel-dompdf`) — actively used by `CustomsDeclarationGenerator` for the customs/FX-confirmation declaration PDF (`resources/views/pdf/customs-declaration.blade.php`, font: `DejaVu Sans`, DomPDF's bundled default).
- **mPDF** (`mpdf/mpdf`) — registered as a singleton in `AppServiceProvider` (`PdfGeneratorService::class`) but **never called anywhere** — no controller, job, service, or test references `PdfGeneratorService`. Confirmed via a full-repo grep for both the class name and `Mpdf\`/`use Mpdf` imports outside the service itself and the DI registration.
- `resources/fonts/IBMPlexSansArabic-{Regular,Bold,SemiBold}.ttf` and `Amiri-{Regular,Bold}.ttf` exist solely to support `PdfGeneratorService`'s mPDF config (`fontDir`/`fontdata` in `makeMpdf()`) — the active DomPDF path uses `DejaVu Sans` and does not reference these files.

So the actual state is: one engine in production use, one engine (with its own dependency + dedicated font assets) that is dead code, not two engines each serving a live document type.

## Why no change was made this pass

Confirmed with the user before acting (package/dependency removal, even of dead code, is more consequential than the query/config-level fixes in the rest of this remediation pass). Decision: leave PDF engines as-is for now; record this finding accurately so a future pass can act on it without re-investigating.

## Recommendation for a future pass

If mPDF truly stays unused, remove `PdfGeneratorService`, its `AppServiceProvider` registration, the `mpdf/mpdf` composer dependency, and the `IBMPlexSansArabic-*`/`Amiri-*` font files (unless another future document intentionally wants mPDF's stronger Arabic OTL/kashida shaping — in which case, keep mPDF and instead point that future document generator at `PdfGeneratorService` rather than leaving it orphaned). Either way, do not touch `CustomsDeclarationGenerator`/DomPDF — it is the actively-used path and out of scope for a "remove dead code" cleanup.

The finding's second half (API namespace split: `Api\V1\*` vs unversioned `Api\*`) was not investigated further this pass — remains open, unscoped, larger/higher-risk refactor per the finding's own "record, don't force" guidance.
