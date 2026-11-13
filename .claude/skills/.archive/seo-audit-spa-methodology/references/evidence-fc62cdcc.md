$ git diff app/Domains/Entities/Controllers/CategoryShowController.php
+            // The entity page reads its 365d snapshot whenever one exists, so an entity eligible
+            // only all-time but thin over 365d has a noindex page: do not link it.
+            $thinOverOneYear = SentimentSnapshot::query()
...
noindex targets: 8
      3 /top/technology
      3 /top/digital-services
      1 /top/fmcg
      1 /
/e/awan-forge-website-design-and-custom-software-development content="noindex, follow"
/e/datamaya content="noindex, follow"
/e/fabriku-operasional-umkm-dalam-satu-alur content="noindex, follow"