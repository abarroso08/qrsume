-- Drop the old profile visit counter.
--
-- personalinfo.visits was incremented on every load of index.php (including
-- the owner's own visits and refreshes). Profile views now come only from
-- user_statistics.views, written by recordPageView() in assets/stats.php.
--
-- Run this AFTER deploying the PHP that no longer references the column,
-- otherwise the old UPDATE in index.php will fail.

ALTER TABLE personalinfo DROP COLUMN visits;
