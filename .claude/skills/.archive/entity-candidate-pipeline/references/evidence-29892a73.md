                // Missing means unsure: only used to gate auto-approval, so default to the safe side.
            'sold_in_indonesia' => $isRelevant && (bool) ($suggestion['sold_in_indonesia'] ?? false),
            if ($soldInIndonesia && $this->autoApprove($candidate)) {
            // e.g. a slug clash: leave it pending for an admin rather than abort the scan.