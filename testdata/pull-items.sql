SELECT
    `resource`.`id` AS `id`,
    `resource`.`title` AS `title`,
    GROUP_CONCAT(
        CONCAT(
            `vocabulary`.`prefix`,
            '_',
            `property`.`local_name`
        ) SEPARATOR "|"
    ) AS `fields`,
    GROUP_CONCAT(`value`.`value` SEPARATOR "|") AS `values`,
    LENGTH(`value`.`value`) AS `values_count`
FROM
    `value` AS `value`
    LEFT JOIN `resource` ON `resource`.`id` = `value`.`resource_id`
    LEFT JOIN `property` ON `property`.`id` = `value`.`property_id`
    LEFT JOIN `vocabulary` ON `vocabulary`.`id` = `property`.`vocabulary_id`
WHERE
    `resource`.`resource_type` = 'Omeka\\Entity\\Item'
GROUP BY
    `resource`.`id`
INTO OUTFILE '/tmp/data.csv'
FIELDS TERMINATED BY ','
ENCLOSED BY '"'
LINES TERMINATED BY '\n';