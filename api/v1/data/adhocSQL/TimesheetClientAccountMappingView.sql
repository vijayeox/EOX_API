CREATE OR REPLACE
ALGORITHM = UNDEFINED VIEW `oxzionapi`.`User_Account_Client_Mapping` AS
select
    `oau`.`id` AS `id`,
    3 AS `account_id`,
    `oau`.`user_id` AS `user_id`,
    `oau`.`account_id` AS `accountId`,
    `oau`.`account_id` AS `org_id`,
    `oa`.`name` AS `client`
from
    (`oxzionapi`.`ox_account_user` `oau`
join `oxzionapi`.`ox_account` `oa` on
    ((`oa`.`id` = `oau`.`account_id`)))
where
    (`oau`.`account_id` <> 3);
