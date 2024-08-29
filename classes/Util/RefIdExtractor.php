<?php

class RefIdExtractor
{
    public static function getPluginItemDataRefId(string $action): ?int
    {
        if (!preg_match('/(?:ref_id=|_|\b(?:goto\.php|go)\/\w{3,4}\/)(?<ref_id>\d+)/', $action, $matches)) {
            return null;
        }

        return (int) $matches['ref_id'];
    }
}