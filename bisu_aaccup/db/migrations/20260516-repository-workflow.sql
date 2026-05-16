CREATE TABLE IF NOT EXISTS repositories (
  repository_id INT(11) NOT NULL AUTO_INCREMENT,
  repository_name VARCHAR(255) NOT NULL,
  school_year VARCHAR(20) NOT NULL,
  accreditation_year YEAR NOT NULL,
  program_id INT(11) DEFAULT NULL,
  course_type VARCHAR(150) DEFAULT NULL,
  repository_status ENUM('draft', 'in_review', 'approved', 'archived') NOT NULL DEFAULT 'draft',
  created_by INT(11) NOT NULL,
  approved_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (repository_id),
  KEY idx_repositories_program (program_id),
  KEY idx_repositories_status (repository_status),
  KEY idx_repositories_created_by (created_by),
  CONSTRAINT fk_repositories_program FOREIGN KEY (program_id) REFERENCES programs (program_id) ON DELETE SET NULL,
  CONSTRAINT fk_repositories_created_by FOREIGN KEY (created_by) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS repository_members (
  repository_member_id INT(11) NOT NULL AUTO_INCREMENT,
  repository_id INT(11) NOT NULL,
  user_id INT(11) NOT NULL,
  member_role ENUM('focal', 'accreditor') NOT NULL,
  can_upload TINYINT(1) NOT NULL DEFAULT 0,
  can_review TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME DEFAULT NULL,
  PRIMARY KEY (repository_member_id),
  UNIQUE KEY uniq_repository_member (repository_id, user_id, member_role),
  KEY idx_repository_members_user (user_id),
  CONSTRAINT fk_repository_members_repository FOREIGN KEY (repository_id) REFERENCES repositories (repository_id) ON DELETE CASCADE,
  CONSTRAINT fk_repository_members_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS repository_sections (
  section_id INT(11) NOT NULL AUTO_INCREMENT,
  repository_id INT(11) NOT NULL,
  parent_section_id INT(11) DEFAULT NULL,
  section_name VARCHAR(255) NOT NULL,
  section_kind ENUM('folder', 'area') NOT NULL DEFAULT 'folder',
  sort_order INT(11) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (section_id),
  KEY idx_repository_sections_repository (repository_id),
  KEY idx_repository_sections_parent (parent_section_id),
  CONSTRAINT fk_repository_sections_repository FOREIGN KEY (repository_id) REFERENCES repositories (repository_id) ON DELETE CASCADE,
  CONSTRAINT fk_repository_sections_parent FOREIGN KEY (parent_section_id) REFERENCES repository_sections (section_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS repository_documents (
  repository_document_id INT(11) NOT NULL AUTO_INCREMENT,
  repository_id INT(11) NOT NULL,
  section_id INT(11) DEFAULT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) DEFAULT NULL,
  uploaded_by INT(11) NOT NULL,
  document_status ENUM('draft', 'for_review', 'finalized', 'approved') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (repository_document_id),
  KEY idx_repository_documents_repository (repository_id),
  KEY idx_repository_documents_section (section_id),
  KEY idx_repository_documents_uploaded_by (uploaded_by),
  CONSTRAINT fk_repository_documents_repository FOREIGN KEY (repository_id) REFERENCES repositories (repository_id) ON DELETE CASCADE,
  CONSTRAINT fk_repository_documents_section FOREIGN KEY (section_id) REFERENCES repository_sections (section_id) ON DELETE SET NULL,
  CONSTRAINT fk_repository_documents_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS repository_comments (
  repository_comment_id INT(11) NOT NULL AUTO_INCREMENT,
  repository_document_id INT(11) NOT NULL,
  user_id INT(11) NOT NULL,
  comment_text TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (repository_comment_id),
  KEY idx_repository_comments_document (repository_document_id),
  KEY idx_repository_comments_user (user_id),
  CONSTRAINT fk_repository_comments_document FOREIGN KEY (repository_document_id) REFERENCES repository_documents (repository_document_id) ON DELETE CASCADE,
  CONSTRAINT fk_repository_comments_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
