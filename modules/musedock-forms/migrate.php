<?php
/** Explicit installer, never runs on frontend requests. */
return static function(\PDO $db): void {
    $pg=$db->getAttribute(\PDO::ATTR_DRIVER_NAME)==='pgsql';$id=$pg?'BIGSERIAL PRIMARY KEY':'BIGINT AUTO_INCREMENT PRIMARY KEY';$text=$pg?'TEXT':'LONGTEXT';
    $db->exec("CREATE TABLE IF NOT EXISTS md_forms (id $id,tenant_id BIGINT NOT NULL,slug VARCHAR(120) NOT NULL,title VARCHAR(255) NOT NULL,description TEXT NOT NULL DEFAULT '',locale VARCHAR(8) NOT NULL DEFAULT 'es',fields $text NOT NULL,active SMALLINT NOT NULL DEFAULT 1,source VARCHAR(80),source_id VARCHAR(120),legacy_definition $text,UNIQUE(tenant_id,slug),UNIQUE(tenant_id,source,source_id))");
    $db->exec("CREATE TABLE IF NOT EXISTS md_form_submissions (id $id,tenant_id BIGINT NOT NULL,form_id BIGINT NOT NULL,data $text NOT NULL,source VARCHAR(80) NOT NULL DEFAULT 'native',source_id VARCHAR(120),legacy_data $text,privacy_version VARCHAR(120),submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(tenant_id,source,source_id),FOREIGN KEY(form_id) REFERENCES md_forms(id))");
};
