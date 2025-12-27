<?php

namespace TunaSahincomtr\MetaKit\Services;

use TunaSahincomtr\MetaKit\Models\MetaKitPage;

class SeoScoreCalculator
{
    /**
     * Calculate SEO score for a page.
     */
    public function calculate(MetaKitPage $page): array
    {
        $scores = [
            'title' => $this->calculateTitleScore($page),
            'description' => $this->calculateDescriptionScore($page),
            'canonical' => $this->calculateCanonicalScore($page),
            'og_tags' => $this->calculateOgTagsScore($page),
            'twitter_tags' => $this->calculateTwitterTagsScore($page),
            'jsonld' => $this->calculateJsonLdScore($page),
            'breadcrumb' => $this->calculateBreadcrumbScore($page),
        ];

        $totalScore = array_sum($scores);
        $maxScore = 100;

        return [
            'score' => $totalScore,
            'max_score' => $maxScore,
            'percentage' => round(($totalScore / $maxScore) * 100, 1),
            'breakdown' => $scores,
            'recommendations' => $this->getRecommendations($page, $scores),
        ];
    }

    /**
     * Calculate title score (0-20 points).
     */
    protected function calculateTitleScore(MetaKitPage $page): int
    {
        $score = 0;

        if (empty($page->title)) {
            return 0;
        }

        // Title exists (5 points)
        $score += 5;

        // Title length (15 points)
        $length = mb_strlen($page->title);
        
        if ($length >= 50 && $length <= 60) {
            // Perfect length for SEO
            $score += 15;
        } elseif ($length >= 40 && $length < 50) {
            // Good, but could be longer
            $score += 10;
        } elseif ($length > 60 && $length <= 70) {
            // A bit long, but acceptable
            $score += 10;
        } elseif ($length >= 30 && $length < 40) {
            // Too short
            $score += 5;
        } elseif ($length > 70 && $length <= 80) {
            // Too long
            $score += 5;
        }
        // < 30 or > 80: 0 points (too short or too long)

        return min($score, 20);
    }

    /**
     * Calculate description score (0-20 points).
     */
    protected function calculateDescriptionScore(MetaKitPage $page): int
    {
        $score = 0;

        if (empty($page->description)) {
            return 0;
        }

        // Description exists (5 points)
        $score += 5;

        // Description length (15 points)
        $length = mb_strlen($page->description);
        
        if ($length >= 150 && $length <= 160) {
            // Perfect length for SEO
            $score += 15;
        } elseif ($length >= 120 && $length < 150) {
            // Good, but could be longer
            $score += 10;
        } elseif ($length > 160 && $length <= 180) {
            // A bit long, but acceptable
            $score += 10;
        } elseif ($length >= 100 && $length < 120) {
            // Too short
            $score += 5;
        } elseif ($length > 180 && $length <= 200) {
            // Too long
            $score += 5;
        }
        // < 100 or > 200: 0 points (too short or too long)

        return min($score, 20);
    }

    /**
     * Calculate canonical URL score (0-10 points).
     */
    protected function calculateCanonicalScore(MetaKitPage $page): int
    {
        return !empty($page->canonical_url) ? 10 : 0;
    }

    /**
     * Calculate OG tags score (0-25 points).
     */
    protected function calculateOgTagsScore(MetaKitPage $page): int
    {
        $score = 0;

        // OG Title (5 points)
        if (!empty($page->og_title)) {
            $score += 5;
        }

        // OG Description (5 points)
        if (!empty($page->og_description)) {
            $score += 5;
        }

        // OG Image (15 points) - VERY IMPORTANT
        if (!empty($page->og_image)) {
            $score += 15;
        }

        return $score;
    }

    /**
     * Calculate Twitter tags score (0-10 points).
     */
    protected function calculateTwitterTagsScore(MetaKitPage $page): int
    {
        $score = 0;

        // Twitter Card (5 points)
        if (!empty($page->twitter_card)) {
            $score += 5;
        }

        // Twitter Image (5 points)
        if (!empty($page->twitter_image)) {
            $score += 5;
        }

        return $score;
    }

    /**
     * Calculate JSON-LD score (0-10 points).
     */
    protected function calculateJsonLdScore(MetaKitPage $page): int
    {
        return !empty($page->jsonld) && is_array($page->jsonld) && count($page->jsonld) > 0 ? 10 : 0;
    }

    /**
     * Calculate breadcrumb JSON-LD score (0-5 points).
     */
    protected function calculateBreadcrumbScore(MetaKitPage $page): int
    {
        return !empty($page->breadcrumb_jsonld) && is_array($page->breadcrumb_jsonld) ? 5 : 0;
    }

    /**
     * Get recommendations for improving SEO score.
     */
    protected function getRecommendations(MetaKitPage $page, array $scores): array
    {
        $recommendations = [];

        // Title recommendations
        if ($scores['title'] < 20) {
            if (empty($page->title)) {
                $recommendations[] = 'Title ekleyin';
            } else {
                $length = mb_strlen($page->title);
                if ($length < 50) {
                    $recommendations[] = "Title çok kısa ({$length} karakter). 50-60 karakter arası ideal.";
                } elseif ($length > 60) {
                    $recommendations[] = "Title çok uzun ({$length} karakter). 50-60 karakter arası ideal.";
                }
            }
        }

        // Description recommendations
        if ($scores['description'] < 20) {
            if (empty($page->description)) {
                $recommendations[] = 'Description ekleyin';
            } else {
                $length = mb_strlen($page->description);
                if ($length < 150) {
                    $recommendations[] = "Description çok kısa ({$length} karakter). 150-160 karakter arası ideal.";
                } elseif ($length > 160) {
                    $recommendations[] = "Description çok uzun ({$length} karakter). 150-160 karakter arası ideal.";
                }
            }
        }

        // Canonical recommendations
        if ($scores['canonical'] < 10) {
            $recommendations[] = 'Canonical URL ekleyin';
        }

        // OG tags recommendations
        if ($scores['og_tags'] < 25) {
            if (empty($page->og_title)) {
                $recommendations[] = 'OG Title ekleyin';
            }
            if (empty($page->og_description)) {
                $recommendations[] = 'OG Description ekleyin';
            }
            if (empty($page->og_image)) {
                $recommendations[] = 'OG Image ekleyin (ÇOK ÖNEMLİ - 15 puan)';
            }
        }

        // Twitter tags recommendations
        if ($scores['twitter_tags'] < 10) {
            if (empty($page->twitter_card)) {
                $recommendations[] = 'Twitter Card ekleyin';
            }
            if (empty($page->twitter_image)) {
                $recommendations[] = 'Twitter Image ekleyin';
            }
        }

        // JSON-LD recommendations
        if ($scores['jsonld'] < 10) {
            $recommendations[] = 'JSON-LD schema ekleyin';
        }

        // Breadcrumb recommendations
        if ($scores['breadcrumb'] < 5) {
            $recommendations[] = 'Breadcrumb JSON-LD ekleyin (SEO için faydalı)';
        }

        return $recommendations;
    }
}

