--
-- PostgreSQL database dump
--


-- Dumped from database version 16.14
-- Dumped by pg_dump version 16.14

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: accrual_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.accrual_logs (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    user_id bigint NOT NULL,
    leave_type_id bigint NOT NULL,
    accrual_policy_id bigint,
    leave_balance_id bigint,
    type character varying(64) NOT NULL,
    amount numeric(8,2) NOT NULL,
    balance_before numeric(8,2) NOT NULL,
    balance_after numeric(8,2) NOT NULL,
    description text,
    effective_date date NOT NULL,
    reference_type character varying(255) NOT NULL,
    reference_id bigint NOT NULL,
    created_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT accrual_logs_type_check CHECK (((type)::text = ANY (ARRAY[('accrual'::character varying)::text, ('usage'::character varying)::text, ('adjustment'::character varying)::text, ('carryover'::character varying)::text, ('expiry'::character varying)::text, ('encashment'::character varying)::text, ('initial_grant'::character varying)::text])))
);


--
-- Name: accrual_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.accrual_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: accrual_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.accrual_logs_id_seq OWNED BY public.accrual_logs.id;


--
-- Name: accrual_policies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.accrual_policies (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    leave_type_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    accrual_type character varying(64) DEFAULT 'annual'::character varying NOT NULL,
    accrual_rate numeric(8,2) NOT NULL,
    max_balance numeric(8,2),
    min_balance numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    tenure_rules jsonb,
    allow_carryover boolean DEFAULT true NOT NULL,
    max_carryover_days numeric(8,2),
    carryover_expiry_date date,
    allow_encashment boolean DEFAULT false NOT NULL,
    max_encashment_days numeric(8,2),
    encashment_rate numeric(8,2) DEFAULT '1'::numeric NOT NULL,
    waiting_period_days integer DEFAULT 0 NOT NULL,
    prorate_first_year boolean DEFAULT true NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT accrual_policies_accrual_type_check CHECK (((accrual_type)::text = ANY (ARRAY[('annual'::character varying)::text, ('monthly'::character varying)::text, ('per_pay_period'::character varying)::text, ('hourly'::character varying)::text, ('custom'::character varying)::text])))
);


--
-- Name: accrual_policies_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.accrual_policies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: accrual_policies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.accrual_policies_id_seq OWNED BY public.accrual_policies.id;


--
-- Name: activity_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.activity_logs (
    id bigint NOT NULL,
    company_id bigint,
    user_id bigint,
    user_name character varying(255),
    action character varying(255) NOT NULL,
    model_type character varying(255),
    model_id bigint,
    description character varying(255),
    old_values jsonb,
    new_values jsonb,
    ip_address character varying(45),
    user_agent text,
    url character varying(255),
    method character varying(10),
    is_successful boolean DEFAULT true NOT NULL,
    error_message text,
    created_at timestamp with time zone
);


--
-- Name: activity_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.activity_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: activity_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.activity_logs_id_seq OWNED BY public.activity_logs.id;


--
-- Name: announcement_reads; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.announcement_reads (
    id bigint NOT NULL,
    announcement_id bigint NOT NULL,
    employee_id bigint NOT NULL,
    read_at timestamp with time zone NOT NULL,
    acknowledged boolean DEFAULT false NOT NULL,
    acknowledged_at timestamp with time zone,
    user_id bigint
);


--
-- Name: announcement_reads_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.announcement_reads_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: announcement_reads_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.announcement_reads_id_seq OWNED BY public.announcement_reads.id;


--
-- Name: announcements; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.announcements (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    content text NOT NULL,
    summary text,
    type character varying(255) DEFAULT 'general'::character varying NOT NULL,
    category character varying(255),
    is_for_all boolean DEFAULT true NOT NULL,
    target_departments jsonb,
    target_positions jsonb,
    target_employees jsonb,
    image_path character varying(255),
    attachments jsonb,
    is_published boolean DEFAULT false NOT NULL,
    published_at timestamp with time zone,
    expires_at timestamp with time zone,
    is_pinned boolean DEFAULT false NOT NULL,
    pin_order integer DEFAULT 0 NOT NULL,
    view_count integer DEFAULT 0 NOT NULL,
    requires_acknowledgment boolean DEFAULT false NOT NULL,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    target_branches jsonb
);


--
-- Name: announcements_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.announcements_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: announcements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.announcements_id_seq OWNED BY public.announcements.id;


--
-- Name: api_keys; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.api_keys (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    key character varying(64) NOT NULL,
    description text,
    permissions jsonb,
    last_used_at timestamp with time zone,
    expires_at timestamp with time zone,
    is_active boolean DEFAULT true NOT NULL,
    created_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: api_keys_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.api_keys_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: api_keys_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.api_keys_id_seq OWNED BY public.api_keys.id;


--
-- Name: application_forms; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.application_forms (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    fields jsonb NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: application_forms_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.application_forms_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: application_forms_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.application_forms_id_seq OWNED BY public.application_forms.id;


--
-- Name: application_sources; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.application_sources (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(255) NOT NULL,
    type character varying(64) DEFAULT 'other'::character varying NOT NULL,
    cost_per_application numeric(10,2),
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT application_sources_type_check CHECK (((type)::text = ANY (ARRAY[('job_board'::character varying)::text, ('social'::character varying)::text, ('referral'::character varying)::text, ('career_site'::character varying)::text, ('agency'::character varying)::text, ('other'::character varying)::text])))
);


--
-- Name: application_sources_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.application_sources_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: application_sources_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.application_sources_id_seq OWNED BY public.application_sources.id;


--
-- Name: application_status_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.application_status_logs (
    id bigint NOT NULL,
    job_application_id bigint NOT NULL,
    from_status character varying(255),
    to_status character varying(255) NOT NULL,
    note text,
    changed_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: application_status_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.application_status_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: application_status_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.application_status_logs_id_seq OWNED BY public.application_status_logs.id;


--
-- Name: approval_delegations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.approval_delegations (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    delegator_id bigint NOT NULL,
    delegate_id bigint NOT NULL,
    start_date date NOT NULL,
    end_date date NOT NULL,
    entity_type character varying(255),
    is_active boolean DEFAULT true NOT NULL,
    reason text,
    created_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: approval_delegations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.approval_delegations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: approval_delegations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.approval_delegations_id_seq OWNED BY public.approval_delegations.id;


--
-- Name: approval_escalation_alerts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.approval_escalation_alerts (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    approval_record_id bigint NOT NULL,
    alert_level character varying(32) NOT NULL,
    notified_at timestamp with time zone NOT NULL
);


--
-- Name: approval_escalation_alerts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.approval_escalation_alerts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: approval_escalation_alerts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.approval_escalation_alerts_id_seq OWNED BY public.approval_escalation_alerts.id;


--
-- Name: approval_instances; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.approval_instances (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    approval_workflow_id bigint NOT NULL,
    approvable_type character varying(255) NOT NULL,
    approvable_id bigint NOT NULL,
    current_step integer DEFAULT 1 NOT NULL,
    status character varying(32) DEFAULT 'pending'::character varying NOT NULL,
    started_at timestamp with time zone,
    completed_at timestamp with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT approval_instances_status_check CHECK (((status)::text = ANY (ARRAY[('pending'::character varying)::text, ('in_progress'::character varying)::text, ('approved'::character varying)::text, ('rejected'::character varying)::text, ('cancelled'::character varying)::text])))
);


--
-- Name: approval_instances_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.approval_instances_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: approval_instances_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.approval_instances_id_seq OWNED BY public.approval_instances.id;


--
-- Name: approval_records; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.approval_records (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    approval_workflow_id bigint NOT NULL,
    approval_step_id bigint NOT NULL,
    approvable_type character varying(255) NOT NULL,
    approvable_id bigint NOT NULL,
    approver_id bigint,
    status character varying(64) DEFAULT 'pending'::character varying NOT NULL,
    comment text,
    decided_at timestamp with time zone,
    step_order integer NOT NULL,
    is_current boolean DEFAULT false NOT NULL,
    escalated_at timestamp with time zone,
    escalated_to bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    approval_instance_id bigint,
    CONSTRAINT approval_records_status_check CHECK (((status)::text = ANY (ARRAY[('pending'::character varying)::text, ('approved'::character varying)::text, ('rejected'::character varying)::text, ('skipped'::character varying)::text, ('escalated'::character varying)::text])))
);


--
-- Name: approval_records_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.approval_records_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: approval_records_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.approval_records_id_seq OWNED BY public.approval_records.id;


--
-- Name: approval_steps; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.approval_steps (
    id bigint NOT NULL,
    approval_workflow_id bigint NOT NULL,
    step_order integer NOT NULL,
    name character varying(255) NOT NULL,
    approver_type character varying(64) NOT NULL,
    specific_user_id bigint,
    specific_role character varying(255),
    is_required boolean DEFAULT true NOT NULL,
    can_skip boolean DEFAULT false NOT NULL,
    timeout_hours integer,
    timeout_action character varying(64),
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    condition jsonb,
    parallel_group integer,
    completion_policy character varying(16) DEFAULT 'all'::character varying,
    escalation_days smallint,
    CONSTRAINT approval_steps_approver_type_check CHECK (((approver_type)::text = ANY (ARRAY[('direct_manager'::character varying)::text, ('department_head'::character varying)::text, ('specific_user'::character varying)::text, ('specific_role'::character varying)::text, ('hr'::character varying)::text, ('cfo'::character varying)::text, ('ceo'::character varying)::text, ('dynamic_manager'::character varying)::text, ('dynamic_skip_manager'::character varying)::text, ('role'::character varying)::text, ('user'::character varying)::text]))),
    CONSTRAINT approval_steps_completion_policy_check CHECK (((completion_policy)::text = ANY (ARRAY[('all'::character varying)::text, ('any'::character varying)::text]))),
    CONSTRAINT approval_steps_timeout_action_check CHECK (((timeout_action)::text = ANY (ARRAY[('escalate'::character varying)::text, ('auto_approve'::character varying)::text, ('auto_reject'::character varying)::text])))
);


--
-- Name: approval_steps_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.approval_steps_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: approval_steps_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.approval_steps_id_seq OWNED BY public.approval_steps.id;


--
-- Name: approval_workflows; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.approval_workflows (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    entity_type character varying(255) NOT NULL,
    description text,
    is_active boolean DEFAULT true NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    conditions jsonb,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    escalation_days smallint
);


--
-- Name: approval_workflows_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.approval_workflows_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: approval_workflows_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.approval_workflows_id_seq OWNED BY public.approval_workflows.id;


--
-- Name: asset_assignments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.asset_assignments (
    id bigint NOT NULL,
    asset_id bigint NOT NULL,
    user_id bigint NOT NULL,
    assigned_date date NOT NULL,
    return_date date,
    notes text,
    condition_at_assignment character varying(64),
    condition_at_return character varying(64),
    assigned_by bigint,
    returned_to bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT asset_assignments_condition_at_assignment_check CHECK (((condition_at_assignment)::text = ANY (ARRAY[('new'::character varying)::text, ('good'::character varying)::text, ('fair'::character varying)::text, ('poor'::character varying)::text]))),
    CONSTRAINT asset_assignments_condition_at_return_check CHECK (((condition_at_return)::text = ANY (ARRAY[('good'::character varying)::text, ('fair'::character varying)::text, ('poor'::character varying)::text, ('broken'::character varying)::text])))
);


--
-- Name: asset_assignments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.asset_assignments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: asset_assignments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.asset_assignments_id_seq OWNED BY public.asset_assignments.id;


--
-- Name: asset_categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.asset_categories (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    icon character varying(255),
    is_active boolean DEFAULT true NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: asset_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.asset_categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: asset_categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.asset_categories_id_seq OWNED BY public.asset_categories.id;


--
-- Name: asset_maintenance; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.asset_maintenance (
    id bigint NOT NULL,
    asset_id bigint NOT NULL,
    type character varying(64) DEFAULT 'corrective'::character varying NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    scheduled_date date,
    completed_date date,
    cost numeric(10,2),
    vendor character varying(255),
    status character varying(64) DEFAULT 'scheduled'::character varying NOT NULL,
    resolution text,
    created_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT asset_maintenance_status_check CHECK (((status)::text = ANY (ARRAY[('scheduled'::character varying)::text, ('in_progress'::character varying)::text, ('completed'::character varying)::text, ('cancelled'::character varying)::text]))),
    CONSTRAINT asset_maintenance_type_check CHECK (((type)::text = ANY (ARRAY[('preventive'::character varying)::text, ('corrective'::character varying)::text, ('upgrade'::character varying)::text])))
);


--
-- Name: asset_maintenance_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.asset_maintenance_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: asset_maintenance_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.asset_maintenance_id_seq OWNED BY public.asset_maintenance.id;


--
-- Name: asset_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.asset_requests (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    user_id bigint NOT NULL,
    asset_category_id bigint,
    item_name character varying(255) NOT NULL,
    description text,
    justification text NOT NULL,
    urgency character varying(64) DEFAULT 'medium'::character varying NOT NULL,
    needed_by date,
    status character varying(64) DEFAULT 'pending'::character varying NOT NULL,
    approval_notes text,
    approved_by bigint,
    approved_at timestamp with time zone,
    fulfilled_with_asset_id bigint,
    approval_workflow_id bigint,
    current_step integer,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT asset_requests_status_check CHECK (((status)::text = ANY (ARRAY[('pending'::character varying)::text, ('approved'::character varying)::text, ('rejected'::character varying)::text, ('fulfilled'::character varying)::text, ('cancelled'::character varying)::text]))),
    CONSTRAINT asset_requests_urgency_check CHECK (((urgency)::text = ANY (ARRAY[('low'::character varying)::text, ('medium'::character varying)::text, ('high'::character varying)::text, ('critical'::character varying)::text])))
);


--
-- Name: asset_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.asset_requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: asset_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.asset_requests_id_seq OWNED BY public.asset_requests.id;


--
-- Name: assets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.assets (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    category_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    asset_code character varying(255),
    serial_number character varying(255),
    brand character varying(255),
    model character varying(255),
    description text,
    purchase_date date,
    purchase_price numeric(12,2),
    warranty_end_date date,
    condition character varying(64) DEFAULT 'new'::character varying NOT NULL,
    status character varying(64) DEFAULT 'available'::character varying NOT NULL,
    location character varying(255),
    specifications jsonb,
    created_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    depreciation_method character varying(64) DEFAULT 'none'::character varying NOT NULL,
    useful_life_years integer,
    residual_value numeric(15,2),
    current_value numeric(15,2),
    last_depreciation_date date,
    qr_code character varying(255),
    barcode character varying(255),
    lifecycle_stage character varying(64) DEFAULT 'new'::character varying NOT NULL,
    disposed_at date,
    disposal_notes text,
    custom_fields jsonb,
    CONSTRAINT assets_condition_check CHECK (((condition)::text = ANY (ARRAY[('new'::character varying)::text, ('good'::character varying)::text, ('fair'::character varying)::text, ('poor'::character varying)::text, ('broken'::character varying)::text]))),
    CONSTRAINT assets_depreciation_method_check CHECK (((depreciation_method)::text = ANY (ARRAY[('none'::character varying)::text, ('straight_line'::character varying)::text, ('declining_balance'::character varying)::text]))),
    CONSTRAINT assets_lifecycle_stage_check CHECK (((lifecycle_stage)::text = ANY (ARRAY[('new'::character varying)::text, ('active'::character varying)::text, ('maintenance'::character varying)::text, ('retired'::character varying)::text, ('disposed'::character varying)::text]))),
    CONSTRAINT assets_status_check CHECK (((status)::text = ANY (ARRAY[('available'::character varying)::text, ('assigned'::character varying)::text, ('maintenance'::character varying)::text, ('disposed'::character varying)::text])))
);


--
-- Name: assets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.assets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: assets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.assets_id_seq OWNED BY public.assets.id;


--
-- Name: attendance_kiosk_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.attendance_kiosk_tokens (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint,
    jti uuid NOT NULL,
    token_hash character varying(64) NOT NULL,
    expires_at timestamp with time zone NOT NULL,
    used_at timestamp with time zone,
    used_by_user_id bigint,
    created_by bigint,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: attendance_kiosk_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.attendance_kiosk_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: attendance_kiosk_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.attendance_kiosk_tokens_id_seq OWNED BY public.attendance_kiosk_tokens.id;


--
-- Name: attendance_records; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.attendance_records (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    user_id bigint NOT NULL,
    date date NOT NULL,
    clock_in time(0) without time zone,
    clock_out time(0) without time zone,
    break_start time(0) without time zone,
    break_end time(0) without time zone,
    total_hours numeric(5,2),
    overtime_hours numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    clock_in_method character varying(255) DEFAULT 'manual'::character varying NOT NULL,
    clock_out_method character varying(255),
    clock_in_latitude numeric(10,7),
    clock_in_longitude numeric(10,7),
    clock_out_latitude numeric(10,7),
    clock_out_longitude numeric(10,7),
    clock_in_ip character varying(255),
    clock_out_ip character varying(255),
    status character varying(255) DEFAULT 'present'::character varying NOT NULL,
    notes text,
    is_approved boolean DEFAULT false NOT NULL,
    approved_by bigint,
    approved_at timestamp with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    source character varying(32),
    branch_id bigint,
    device_info character varying(255),
    late_minutes integer DEFAULT 0 NOT NULL,
    early_leave_minutes integer DEFAULT 0 NOT NULL,
    missing_minutes integer DEFAULT 0 NOT NULL
);


--
-- Name: attendance_records_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.attendance_records_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: attendance_records_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.attendance_records_id_seq OWNED BY public.attendance_records.id;


--
-- Name: branches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.branches (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(255),
    address text,
    city character varying(255),
    district character varying(255),
    postal_code character varying(255),
    country character varying(255) DEFAULT 'Türkiye'::character varying NOT NULL,
    phone character varying(255),
    email character varying(255),
    manager_id bigint,
    is_active boolean DEFAULT true NOT NULL,
    is_headquarters boolean DEFAULT false NOT NULL,
    latitude numeric(10,8),
    longitude numeric(11,8),
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: branches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.branches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: branches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.branches_id_seq OWNED BY public.branches.id;


--
-- Name: buddy_assignments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.buddy_assignments (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    onboarding_process_id bigint NOT NULL,
    new_hire_id bigint NOT NULL,
    buddy_id bigint NOT NULL,
    start_date date NOT NULL,
    end_date date,
    status character varying(64) DEFAULT 'active'::character varying NOT NULL,
    notes text,
    assigned_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT buddy_assignments_status_check CHECK (((status)::text = ANY (ARRAY[('active'::character varying)::text, ('completed'::character varying)::text, ('cancelled'::character varying)::text])))
);


--
-- Name: buddy_assignments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.buddy_assignments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: buddy_assignments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.buddy_assignments_id_seq OWNED BY public.buddy_assignments.id;


--
-- Name: buddy_pool; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.buddy_pool (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    user_id bigint NOT NULL,
    max_mentees integer DEFAULT 3 NOT NULL,
    current_mentees integer DEFAULT 0 NOT NULL,
    expertise_areas jsonb,
    is_available boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: buddy_pool_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.buddy_pool_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: buddy_pool_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.buddy_pool_id_seq OWNED BY public.buddy_pool.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: candidate_scores; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.candidate_scores (
    id bigint NOT NULL,
    job_application_id bigint NOT NULL,
    job_position_id bigint NOT NULL,
    overall_score numeric(5,2) NOT NULL,
    skill_matches jsonb,
    experience_score jsonb,
    education_score jsonb,
    keyword_matches jsonb,
    summary text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: candidate_scores_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.candidate_scores_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: candidate_scores_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.candidate_scores_id_seq OWNED BY public.candidate_scores.id;


--
-- Name: companies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.companies (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    legal_name character varying(255),
    tax_office character varying(255),
    tax_number character varying(255),
    phone character varying(255),
    email character varying(255),
    website character varying(255),
    address text,
    city character varying(255),
    district character varying(255),
    postal_code character varying(255),
    country character varying(255) DEFAULT 'Türkiye'::character varying NOT NULL,
    sector character varying(255),
    employee_count character varying(255),
    logo character varying(255),
    settings jsonb,
    package_type character varying(64) DEFAULT 'starter'::character varying NOT NULL,
    user_limit integer DEFAULT 5 NOT NULL,
    storage_limit bigint DEFAULT '1073741824'::bigint NOT NULL,
    license_start_date date,
    license_end_date date,
    status character varying(64) DEFAULT 'trial'::character varying NOT NULL,
    trial_ends_at timestamp with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    license_package_id bigint,
    location_count integer DEFAULT 1 NOT NULL,
    location_limit integer DEFAULT 1 NOT NULL,
    employee_limit integer DEFAULT 50 NOT NULL,
    current_balance numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    organization_id bigint,
    CONSTRAINT companies_package_type_check CHECK (((package_type)::text = ANY (ARRAY[('starter'::character varying)::text, ('professional'::character varying)::text, ('enterprise'::character varying)::text]))),
    CONSTRAINT companies_status_check CHECK (((status)::text = ANY (ARRAY[('active'::character varying)::text, ('suspended'::character varying)::text, ('cancelled'::character varying)::text, ('trial'::character varying)::text])))
);


--
-- Name: companies_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.companies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: companies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.companies_id_seq OWNED BY public.companies.id;


--
-- Name: company_ledger; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.company_ledger (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    type character varying(64) NOT NULL,
    amount numeric(12,2) NOT NULL,
    balance_after numeric(12,2) NOT NULL,
    description character varying(255) NOT NULL,
    reference_type character varying(255),
    reference_id bigint,
    payment_method character varying(255),
    payment_reference character varying(255),
    payment_date date,
    invoice_number character varying(255),
    due_date date,
    notes text,
    created_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT company_ledger_type_check CHECK (((type)::text = ANY (ARRAY[('debit'::character varying)::text, ('credit'::character varying)::text])))
);


--
-- Name: company_ledger_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.company_ledger_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: company_ledger_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.company_ledger_id_seq OWNED BY public.company_ledger.id;


--
-- Name: company_modules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.company_modules (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    module_id bigint NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    activated_at date,
    expires_at date,
    settings jsonb,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: company_modules_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.company_modules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: company_modules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.company_modules_id_seq OWNED BY public.company_modules.id;


--
-- Name: company_user; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.company_user (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    company_id bigint NOT NULL,
    role_id bigint,
    is_default boolean DEFAULT false NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: company_user_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.company_user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: company_user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.company_user_id_seq OWNED BY public.company_user.id;


--
-- Name: competencies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.competencies (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    category character varying(255),
    levels jsonb,
    max_level integer DEFAULT 5 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: competencies_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.competencies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: competencies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.competencies_id_seq OWNED BY public.competencies.id;


--
-- Name: consent_records; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.consent_records (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    subject_type character varying(32) NOT NULL,
    subject_id bigint NOT NULL,
    notice_id bigint,
    consent_type character varying(64) NOT NULL,
    granted boolean NOT NULL,
    granted_at timestamp with time zone,
    withdrawn_at timestamp with time zone,
    source character varying(32) NOT NULL,
    ip character varying(45),
    user_agent character varying(512),
    evidence jsonb,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT consent_records_consent_type_check CHECK (((consent_type)::text = ANY (ARRAY[('aydinlatma_okundu'::character varying)::text, ('acik_riza_ozel_nitelikli'::character varying)::text, ('acik_riza_yurtdisi'::character varying)::text, ('ticari_elektronik_ileti'::character varying)::text, ('diger'::character varying)::text]))),
    CONSTRAINT consent_records_source_check CHECK (((source)::text = ANY (ARRAY[('portal'::character varying)::text, ('public_form'::character varying)::text, ('admin'::character varying)::text, ('import'::character varying)::text]))),
    CONSTRAINT consent_records_subject_type_check CHECK (((subject_type)::text = ANY (ARRAY[('employee'::character varying)::text, ('candidate'::character varying)::text, ('visitor'::character varying)::text])))
);


--
-- Name: consent_records_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.consent_records_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: consent_records_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.consent_records_id_seq OWNED BY public.consent_records.id;


--
-- Name: continuous_feedbacks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.continuous_feedbacks (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    from_user_id bigint NOT NULL,
    to_user_id bigint NOT NULL,
    type character varying(64) DEFAULT 'praise'::character varying NOT NULL,
    content text NOT NULL,
    tags jsonb,
    is_public boolean DEFAULT false NOT NULL,
    is_anonymous boolean DEFAULT false NOT NULL,
    related_type character varying(255) NOT NULL,
    related_id bigint NOT NULL,
    created_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT continuous_feedbacks_type_check CHECK (((type)::text = ANY (ARRAY[('praise'::character varying)::text, ('suggestion'::character varying)::text, ('concern'::character varying)::text, ('coaching'::character varying)::text])))
);


--
-- Name: continuous_feedbacks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.continuous_feedbacks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: continuous_feedbacks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.continuous_feedbacks_id_seq OWNED BY public.continuous_feedbacks.id;


--
-- Name: custom_field_definitions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.custom_field_definitions (
    id bigint NOT NULL,
    company_id bigint,
    entity_type character varying(255) NOT NULL,
    field_key character varying(255) NOT NULL,
    field_label character varying(255) NOT NULL,
    field_type character varying(255) NOT NULL,
    field_options jsonb,
    is_required boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    validation_rules jsonb,
    placeholder character varying(255),
    help_text text,
    default_value character varying(255),
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    is_system boolean DEFAULT false NOT NULL,
    system_key character varying(100),
    label_override character varying(255),
    is_hidden boolean DEFAULT false NOT NULL,
    is_required_override boolean,
    field_permission character varying(32),
    CONSTRAINT custom_field_definitions_field_permission_check CHECK (((field_permission)::text = ANY (ARRAY[('readonly'::character varying)::text, ('hidden'::character varying)::text])))
);


--
-- Name: custom_field_definitions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.custom_field_definitions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: custom_field_definitions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.custom_field_definitions_id_seq OWNED BY public.custom_field_definitions.id;


--
-- Name: dashboard_shares; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dashboard_shares (
    id bigint NOT NULL,
    dashboard_id bigint NOT NULL,
    company_id bigint NOT NULL,
    user_id bigint,
    role_id bigint,
    level character varying(16) DEFAULT 'viewer'::character varying NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    department_id bigint,
    CONSTRAINT dashboard_shares_level_check CHECK (((level)::text = ANY (ARRAY[('viewer'::character varying)::text, ('editor'::character varying)::text]))),
    CONSTRAINT dashboard_shares_target_check CHECK ((((user_id IS NOT NULL) AND (role_id IS NULL) AND (department_id IS NULL)) OR ((user_id IS NULL) AND (role_id IS NOT NULL) AND (department_id IS NULL)) OR ((user_id IS NULL) AND (role_id IS NULL) AND (department_id IS NOT NULL))))
);


--
-- Name: dashboard_shares_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dashboard_shares_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dashboard_shares_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dashboard_shares_id_seq OWNED BY public.dashboard_shares.id;


--
-- Name: dashboards; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dashboards (
    id bigint NOT NULL,
    company_id bigint,
    owner_id bigint,
    name character varying(255) NOT NULL,
    description text,
    layout jsonb,
    global_filters jsonb,
    is_system boolean DEFAULT false NOT NULL,
    created_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    folder_id bigint,
    cache_ttl_seconds integer,
    module_key character varying(64),
    system_key character varying(128)
);


--
-- Name: dashboards_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dashboards_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dashboards_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dashboards_id_seq OWNED BY public.dashboards.id;


--
-- Name: data_breaches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.data_breaches (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    detected_at timestamp with time zone NOT NULL,
    occurred_at timestamp with time zone,
    description text NOT NULL,
    affected_categories jsonb,
    affected_subject_count integer DEFAULT 0 NOT NULL,
    severity character varying(32) DEFAULT 'medium'::character varying NOT NULL,
    root_cause text,
    containment_actions text,
    notified_kvkk boolean DEFAULT false NOT NULL,
    notified_kvkk_at timestamp with time zone,
    notified_subjects boolean DEFAULT false NOT NULL,
    notified_subjects_at timestamp with time zone,
    notified_subjects_method character varying(255),
    status character varying(32) DEFAULT 'open'::character varying NOT NULL,
    closed_at timestamp with time zone,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT data_breaches_severity_check CHECK (((severity)::text = ANY (ARRAY[('low'::character varying)::text, ('medium'::character varying)::text, ('high'::character varying)::text, ('critical'::character varying)::text])))
);


--
-- Name: data_breaches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.data_breaches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: data_breaches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.data_breaches_id_seq OWNED BY public.data_breaches.id;


--
-- Name: data_processing_activities; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.data_processing_activities (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    key character varying(64) NOT NULL,
    name character varying(255) NOT NULL,
    data_categories jsonb,
    purpose text,
    legal_basis character varying(64) NOT NULL,
    data_subject_group character varying(32) NOT NULL,
    recipients jsonb,
    retention_period_months integer,
    transfer_abroad boolean DEFAULT false NOT NULL,
    transfer_abroad_note text,
    security_measures text,
    is_system boolean DEFAULT false NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT data_processing_activities_legal_basis_check CHECK (((legal_basis)::text = ANY (ARRAY[('explicit_consent'::character varying)::text, ('contract'::character varying)::text, ('legal_obligation'::character varying)::text, ('legitimate_interest'::character varying)::text, ('legal_provision'::character varying)::text]))),
    CONSTRAINT data_processing_activities_subject_group_check CHECK (((data_subject_group)::text = ANY (ARRAY[('employee'::character varying)::text, ('candidate'::character varying)::text, ('visitor'::character varying)::text, ('contractor'::character varying)::text])))
);


--
-- Name: data_processing_activities_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.data_processing_activities_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: data_processing_activities_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.data_processing_activities_id_seq OWNED BY public.data_processing_activities.id;


--
-- Name: data_subject_export_access_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.data_subject_export_access_logs (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    export_package_id bigint NOT NULL,
    user_id bigint,
    action character varying(32) NOT NULL,
    ip_address character varying(45),
    user_agent text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: data_subject_export_access_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.data_subject_export_access_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: data_subject_export_access_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.data_subject_export_access_logs_id_seq OWNED BY public.data_subject_export_access_logs.id;


--
-- Name: data_subject_export_packages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.data_subject_export_packages (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    data_subject_request_id bigint NOT NULL,
    uuid uuid NOT NULL,
    status character varying(32) DEFAULT 'pending'::character varying NOT NULL,
    storage_path character varying(255),
    json_path character varying(255),
    human_path character varying(255),
    error_message text,
    expires_at timestamp with time zone,
    purged_at timestamp with time zone,
    created_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: data_subject_export_packages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.data_subject_export_packages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: data_subject_export_packages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.data_subject_export_packages_id_seq OWNED BY public.data_subject_export_packages.id;


--
-- Name: data_subject_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.data_subject_requests (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    subject_type character varying(32) NOT NULL,
    subject_id bigint,
    applicant_name character varying(255) NOT NULL,
    contact character varying(255) NOT NULL,
    request_types jsonb NOT NULL,
    description text,
    channel character varying(32) NOT NULL,
    identity_verified boolean DEFAULT false NOT NULL,
    verification_method character varying(64),
    verified_by bigint,
    verified_at timestamp with time zone,
    status character varying(32) DEFAULT 'new'::character varying NOT NULL,
    due_date date NOT NULL,
    responded_at timestamp with time zone,
    response_body text,
    response_file_path character varying(255),
    response_template character varying(32),
    assigned_to bigint,
    rejection_reason text,
    destruction_pending boolean DEFAULT false NOT NULL,
    destruction_scope jsonb,
    email_verify_token character varying(64),
    email_verified_at timestamp with time zone,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT data_subject_requests_channel_check CHECK (((channel)::text = ANY (ARRAY[('portal'::character varying)::text, ('public_form'::character varying)::text, ('email'::character varying)::text, ('written'::character varying)::text, ('kep'::character varying)::text]))),
    CONSTRAINT data_subject_requests_status_check CHECK (((status)::text = ANY (ARRAY[('new'::character varying)::text, ('identity_pending'::character varying)::text, ('in_review'::character varying)::text, ('awaiting_info'::character varying)::text, ('approved'::character varying)::text, ('partially_approved'::character varying)::text, ('rejected'::character varying)::text, ('completed'::character varying)::text]))),
    CONSTRAINT data_subject_requests_subject_type_check CHECK (((subject_type)::text = ANY (ARRAY[('employee'::character varying)::text, ('candidate'::character varying)::text, ('former_employee'::character varying)::text, ('visitor'::character varying)::text, ('other'::character varying)::text])))
);


--
-- Name: data_subject_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.data_subject_requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: data_subject_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.data_subject_requests_id_seq OWNED BY public.data_subject_requests.id;


--
-- Name: departments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.departments (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(255),
    description text,
    manager_id bigint,
    parent_id bigint,
    is_active boolean DEFAULT true NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: departments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.departments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: departments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.departments_id_seq OWNED BY public.departments.id;


--
-- Name: destruction_approvals; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.destruction_approvals (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    approved_by bigint NOT NULL,
    approved_at timestamp with time zone NOT NULL,
    candidate_ids jsonb NOT NULL,
    dry_run_confirmed boolean DEFAULT false NOT NULL,
    status character varying(32) DEFAULT 'approved'::character varying NOT NULL,
    note text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: destruction_approvals_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.destruction_approvals_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: destruction_approvals_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.destruction_approvals_id_seq OWNED BY public.destruction_approvals.id;


--
-- Name: destruction_candidates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.destruction_candidates (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    retention_policy_id bigint,
    subject_type character varying(32) NOT NULL,
    subject_id bigint NOT NULL,
    data_category character varying(64) NOT NULL,
    record_count integer DEFAULT 0 NOT NULL,
    due_since timestamp with time zone,
    status character varying(32) DEFAULT 'pending'::character varying NOT NULL,
    strategy character varying(32) DEFAULT 'anonymize'::character varying NOT NULL,
    preview_snapshot jsonb,
    approval_id bigint,
    skip_reason text,
    deferred_until timestamp with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT destruction_candidates_status_check CHECK (((status)::text = ANY (ARRAY[('pending'::character varying)::text, ('deferred'::character varying)::text, ('excluded'::character varying)::text, ('skipped_legal_hold'::character varying)::text, ('approved'::character varying)::text, ('processing'::character varying)::text, ('completed'::character varying)::text, ('failed'::character varying)::text])))
);


--
-- Name: destruction_candidates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.destruction_candidates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: destruction_candidates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.destruction_candidates_id_seq OWNED BY public.destruction_candidates.id;


--
-- Name: destruction_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.destruction_logs (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    destruction_candidate_id bigint,
    approval_id bigint,
    retention_policy_id bigint,
    subject_type character varying(32) NOT NULL,
    subject_id bigint NOT NULL,
    data_category character varying(64),
    strategy character varying(32) NOT NULL,
    collector_key character varying(64),
    rows_affected integer DEFAULT 0 NOT NULL,
    summary jsonb,
    content_hash character varying(64),
    approved_by bigint,
    dry_run boolean DEFAULT false NOT NULL,
    outcome character varying(32) DEFAULT 'success'::character varying NOT NULL,
    error_message text,
    created_at timestamp with time zone NOT NULL
);


--
-- Name: destruction_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.destruction_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: destruction_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.destruction_logs_id_seq OWNED BY public.destruction_logs.id;


--
-- Name: document_approvals; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_approvals (
    id bigint NOT NULL,
    document_id bigint NOT NULL,
    approver_id bigint NOT NULL,
    status character varying(64) DEFAULT 'pending'::character varying NOT NULL,
    comment text,
    decided_at timestamp with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT document_approvals_status_check CHECK (((status)::text = ANY (ARRAY[('pending'::character varying)::text, ('approved'::character varying)::text, ('rejected'::character varying)::text])))
);


--
-- Name: document_approvals_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.document_approvals_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: document_approvals_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.document_approvals_id_seq OWNED BY public.document_approvals.id;


--
-- Name: document_categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_categories (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    icon character varying(255),
    color character varying(255),
    description text,
    is_active boolean DEFAULT true NOT NULL,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: document_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.document_categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: document_categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.document_categories_id_seq OWNED BY public.document_categories.id;


--
-- Name: document_expiry_alerts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_expiry_alerts (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    employee_document_id bigint NOT NULL,
    threshold_days smallint NOT NULL,
    notified_at timestamp with time zone NOT NULL
);


--
-- Name: document_expiry_alerts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.document_expiry_alerts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: document_expiry_alerts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.document_expiry_alerts_id_seq OWNED BY public.document_expiry_alerts.id;


--
-- Name: document_versions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_versions (
    id bigint NOT NULL,
    document_id bigint NOT NULL,
    version_number integer NOT NULL,
    file_path character varying(255) NOT NULL,
    file_name character varying(255) NOT NULL,
    file_type character varying(255),
    file_size bigint DEFAULT '0'::bigint NOT NULL,
    hash character varying(255),
    change_notes text,
    uploaded_by bigint NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: document_versions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.document_versions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: document_versions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.document_versions_id_seq OWNED BY public.document_versions.id;


--
-- Name: documents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.documents (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    file_name character varying(255) NOT NULL,
    file_path character varying(255) NOT NULL,
    file_size bigint DEFAULT '0'::bigint NOT NULL,
    file_type character varying(255),
    category_id bigint,
    description text,
    version integer DEFAULT 1 NOT NULL,
    uploaded_by bigint,
    metadata jsonb,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    current_version integer DEFAULT 1 NOT NULL,
    validity_date date,
    approval_status character varying(64) DEFAULT 'approved'::character varying NOT NULL,
    requires_approval boolean DEFAULT false NOT NULL,
    CONSTRAINT documents_approval_status_check CHECK (((approval_status)::text = ANY (ARRAY[('draft'::character varying)::text, ('pending'::character varying)::text, ('approved'::character varying)::text, ('rejected'::character varying)::text])))
);


--
-- Name: documents_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.documents_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: documents_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.documents_id_seq OWNED BY public.documents.id;


--
-- Name: employee_dashboards; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.employee_dashboards (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    user_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    widgets jsonb NOT NULL,
    layout_config jsonb,
    is_favorite boolean DEFAULT false NOT NULL,
    is_shared boolean DEFAULT false NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: employee_dashboards_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.employee_dashboards_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: employee_dashboards_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.employee_dashboards_id_seq OWNED BY public.employee_dashboards.id;


--
-- Name: employee_documents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.employee_documents (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    employee_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    category character varying(255) NOT NULL,
    file_path character varying(255) NOT NULL,
    file_name character varying(255) NOT NULL,
    file_type character varying(255),
    file_size bigint,
    issue_date date,
    expiry_date date,
    is_expired boolean DEFAULT false NOT NULL,
    is_visible_to_employee boolean DEFAULT true NOT NULL,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    notes text,
    uploaded_by bigint,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: employee_documents_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.employee_documents_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: employee_documents_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.employee_documents_id_seq OWNED BY public.employee_documents.id;


--
-- Name: employee_request_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.employee_request_history (
    id bigint NOT NULL,
    employee_request_id bigint NOT NULL,
    old_status character varying(255),
    new_status character varying(255) NOT NULL,
    comment text,
    changed_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: employee_request_history_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.employee_request_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: employee_request_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.employee_request_history_id_seq OWNED BY public.employee_request_history.id;


--
-- Name: employee_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.employee_requests (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    employee_id bigint NOT NULL,
    request_type_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    form_data jsonb,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    rejection_reason text,
    attachments jsonb,
    approved_by bigint,
    approved_at timestamp with time zone,
    priority character varying(255) DEFAULT 'normal'::character varying NOT NULL,
    effective_date date,
    due_date date,
    notes text,
    admin_notes text,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: employee_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.employee_requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: employee_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.employee_requests_id_seq OWNED BY public.employee_requests.id;


--
-- Name: employee_shifts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.employee_shifts (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    user_id bigint NOT NULL,
    shift_id bigint NOT NULL,
    date date NOT NULL,
    notes text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: employee_shifts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.employee_shifts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: employee_shifts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.employee_shifts_id_seq OWNED BY public.employee_shifts.id;


--
-- Name: employees; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.employees (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    user_id bigint,
    department_id bigint,
    employee_code character varying(255),
    title character varying(255),
    "position" character varying(255),
    manager_id bigint,
    birth_date date,
    national_id character varying(255),
    gender character varying(255),
    marital_status character varying(255),
    blood_type character varying(255),
    education_level character varying(255),
    personal_email character varying(255),
    personal_phone character varying(255),
    address text,
    city character varying(255),
    district character varying(255),
    postal_code character varying(255),
    emergency_contact_name character varying(255),
    emergency_contact_phone character varying(255),
    emergency_contact_relation character varying(255),
    hire_date date,
    contract_start_date date,
    contract_end_date date,
    contract_type character varying(255),
    work_type character varying(255),
    gross_salary numeric(12,2),
    net_salary numeric(12,2),
    currency character varying(255) DEFAULT 'TRY'::character varying NOT NULL,
    bank_name character varying(255),
    iban character varying(255),
    sgk_number character varying(255),
    sgk_start_date date,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    termination_date date,
    termination_reason character varying(255),
    notes text,
    custom_fields jsonb,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    branch_id bigint,
    full_name character varying(255),
    position_id bigint
);


--
-- Name: employees_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.employees_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: employees_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.employees_id_seq OWNED BY public.employees.id;


--
-- Name: enps_records; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.enps_records (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    survey_id bigint,
    period_date date NOT NULL,
    promoters integer DEFAULT 0 NOT NULL,
    passives integer DEFAULT 0 NOT NULL,
    detractors integer DEFAULT 0 NOT NULL,
    total_responses integer DEFAULT 0 NOT NULL,
    enps_score numeric(5,2),
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: enps_records_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.enps_records_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: enps_records_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.enps_records_id_seq OWNED BY public.enps_records.id;


--
-- Name: expense_categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.expense_categories (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(255),
    description text,
    max_amount numeric(12,2),
    requires_receipt boolean DEFAULT true NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: expense_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.expense_categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: expense_categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.expense_categories_id_seq OWNED BY public.expense_categories.id;


--
-- Name: expense_claims; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.expense_claims (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    user_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    claim_number character varying(255) NOT NULL,
    expense_date date NOT NULL,
    total_amount numeric(12,2) NOT NULL,
    currency character varying(255) DEFAULT 'TRY'::character varying NOT NULL,
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    rejection_reason text,
    submitted_by bigint,
    submitted_at timestamp with time zone,
    approved_by bigint,
    approved_at timestamp with time zone,
    paid_by bigint,
    paid_at timestamp with time zone,
    payment_method character varying(255),
    payment_reference character varying(255),
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    custom_fields jsonb
);


--
-- Name: expense_claims_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.expense_claims_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: expense_claims_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.expense_claims_id_seq OWNED BY public.expense_claims.id;


--
-- Name: expense_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.expense_items (
    id bigint NOT NULL,
    expense_claim_id bigint NOT NULL,
    expense_category_id bigint NOT NULL,
    description character varying(255) NOT NULL,
    item_date date NOT NULL,
    amount numeric(12,2) NOT NULL,
    currency character varying(255) DEFAULT 'TRY'::character varying NOT NULL,
    receipt_path character varying(255),
    receipt_number character varying(255),
    vendor_name character varying(255),
    notes text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: expense_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.expense_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: expense_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.expense_items_id_seq OWNED BY public.expense_items.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: feedback_providers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.feedback_providers (
    id bigint NOT NULL,
    performance_review_id bigint NOT NULL,
    provider_id bigint NOT NULL,
    relationship character varying(64) NOT NULL,
    status character varying(64) DEFAULT 'pending'::character varying NOT NULL,
    invited_at timestamp with time zone,
    submitted_at timestamp with time zone,
    deadline timestamp with time zone,
    decline_reason text,
    is_anonymous boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT feedback_providers_relationship_check CHECK (((relationship)::text = ANY (ARRAY[('self'::character varying)::text, ('manager'::character varying)::text, ('peer'::character varying)::text, ('direct_report'::character varying)::text, ('external'::character varying)::text]))),
    CONSTRAINT feedback_providers_status_check CHECK (((status)::text = ANY (ARRAY[('pending'::character varying)::text, ('in_progress'::character varying)::text, ('submitted'::character varying)::text, ('declined'::character varying)::text])))
);


--
-- Name: feedback_providers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.feedback_providers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: feedback_providers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.feedback_providers_id_seq OWNED BY public.feedback_providers.id;


--
-- Name: feedback_responses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.feedback_responses (
    id bigint NOT NULL,
    feedback_provider_id bigint NOT NULL,
    performance_criteria_id bigint NOT NULL,
    score integer,
    comment text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: feedback_responses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.feedback_responses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: feedback_responses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.feedback_responses_id_seq OWNED BY public.feedback_responses.id;


--
-- Name: form_definitions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.form_definitions (
    id bigint NOT NULL,
    company_id bigint,
    entity_type character varying(64) NOT NULL,
    name character varying(255) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    layout jsonb NOT NULL,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: form_definitions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.form_definitions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: form_definitions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.form_definitions_id_seq OWNED BY public.form_definitions.id;


--
-- Name: holidays; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.holidays (
    id bigint NOT NULL,
    company_id bigint,
    name character varying(255) NOT NULL,
    date date NOT NULL,
    end_date date,
    type character varying(64) DEFAULT 'national'::character varying NOT NULL,
    country_code character varying(2) DEFAULT 'TR'::character varying NOT NULL,
    is_recurring boolean DEFAULT false NOT NULL,
    is_half_day boolean DEFAULT false NOT NULL,
    description text,
    is_active boolean DEFAULT true NOT NULL,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT holidays_type_check CHECK (((type)::text = ANY (ARRAY[('national'::character varying)::text, ('religious'::character varying)::text, ('company'::character varying)::text, ('regional'::character varying)::text])))
);


--
-- Name: holidays_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.holidays_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: holidays_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.holidays_id_seq OWNED BY public.holidays.id;


--
-- Name: interview_scorecards; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.interview_scorecards (
    id bigint NOT NULL,
    interview_id bigint NOT NULL,
    criteria_name character varying(255) NOT NULL,
    score integer,
    notes text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: interview_scorecards_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.interview_scorecards_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: interview_scorecards_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.interview_scorecards_id_seq OWNED BY public.interview_scorecards.id;


--
-- Name: interviews; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.interviews (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    job_application_id bigint NOT NULL,
    job_position_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    type character varying(64) DEFAULT 'onsite'::character varying NOT NULL,
    scheduled_at timestamp with time zone NOT NULL,
    duration_minutes integer DEFAULT 60 NOT NULL,
    location character varying(255),
    meeting_link character varying(255),
    status character varying(64) DEFAULT 'scheduled'::character varying NOT NULL,
    notes text,
    overall_rating integer,
    recommendation character varying(64),
    feedback text,
    interviewer_id bigint NOT NULL,
    created_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT interviews_recommendation_check CHECK (((recommendation)::text = ANY (ARRAY[('strong_hire'::character varying)::text, ('hire'::character varying)::text, ('no_decision'::character varying)::text, ('no_hire'::character varying)::text, ('strong_no_hire'::character varying)::text]))),
    CONSTRAINT interviews_status_check CHECK (((status)::text = ANY (ARRAY[('scheduled'::character varying)::text, ('completed'::character varying)::text, ('cancelled'::character varying)::text, ('no_show'::character varying)::text, ('rescheduled'::character varying)::text]))),
    CONSTRAINT interviews_type_check CHECK (((type)::text = ANY (ARRAY[('phone'::character varying)::text, ('video'::character varying)::text, ('onsite'::character varying)::text, ('technical'::character varying)::text, ('hr'::character varying)::text, ('panel'::character varying)::text])))
);


--
-- Name: interviews_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.interviews_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: interviews_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.interviews_id_seq OWNED BY public.interviews.id;


--
-- Name: job_applications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_applications (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    job_position_id bigint NOT NULL,
    first_name character varying(255) NOT NULL,
    last_name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    phone character varying(255),
    cv_path character varying(255),
    cv_original_name character varying(255),
    form_data jsonb,
    status character varying(64) DEFAULT 'new'::character varying NOT NULL,
    rating integer,
    notes text,
    internal_notes text,
    source character varying(255),
    referrer character varying(255),
    assigned_to bigint,
    ip_address character varying(255),
    user_agent character varying(255),
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    source_id bigint,
    match_score numeric(5,2),
    parsed_cv_data jsonb,
    last_contacted_at timestamp with time zone,
    consent_kvkk boolean DEFAULT false NOT NULL,
    consent_at timestamp with time zone,
    converted_employee_id bigint,
    CONSTRAINT job_applications_status_check CHECK (((status)::text = ANY (ARRAY[('new'::character varying)::text, ('reviewing'::character varying)::text, ('shortlisted'::character varying)::text, ('interview_scheduled'::character varying)::text, ('interviewed'::character varying)::text, ('offer_sent'::character varying)::text, ('hired'::character varying)::text, ('rejected'::character varying)::text, ('withdrawn'::character varying)::text])))
);


--
-- Name: job_applications_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.job_applications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: job_applications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.job_applications_id_seq OWNED BY public.job_applications.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: job_offers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_offers (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    job_application_id bigint NOT NULL,
    job_position_id bigint NOT NULL,
    salary_offered numeric(15,2),
    currency character varying(3) DEFAULT 'TRY'::character varying NOT NULL,
    start_date date NOT NULL,
    valid_until date NOT NULL,
    benefits jsonb,
    additional_terms text,
    document_path character varying(255),
    status character varying(64) DEFAULT 'draft'::character varying NOT NULL,
    sent_at timestamp with time zone,
    responded_at timestamp with time zone,
    rejection_reason text,
    created_by bigint,
    approved_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT job_offers_status_check CHECK (((status)::text = ANY (ARRAY[('draft'::character varying)::text, ('sent'::character varying)::text, ('accepted'::character varying)::text, ('rejected'::character varying)::text, ('expired'::character varying)::text, ('withdrawn'::character varying)::text])))
);


--
-- Name: job_offers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.job_offers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: job_offers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.job_offers_id_seq OWNED BY public.job_offers.id;


--
-- Name: job_positions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_positions (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    description text,
    requirements text,
    responsibilities text,
    department character varying(255),
    location character varying(255),
    employment_type character varying(64) DEFAULT 'full_time'::character varying NOT NULL,
    experience_level character varying(64) DEFAULT 'mid'::character varying NOT NULL,
    salary_min numeric(10,2),
    salary_max numeric(10,2),
    salary_visible boolean DEFAULT false NOT NULL,
    form_id bigint,
    status character varying(64) DEFAULT 'draft'::character varying NOT NULL,
    positions_count integer DEFAULT 1 NOT NULL,
    application_deadline date,
    published_at timestamp with time zone,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    form_definition_id bigint,
    CONSTRAINT job_positions_experience_level_check CHECK (((experience_level)::text = ANY (ARRAY[('entry'::character varying)::text, ('mid'::character varying)::text, ('senior'::character varying)::text, ('lead'::character varying)::text, ('manager'::character varying)::text]))),
    CONSTRAINT job_positions_status_check CHECK (((status)::text = ANY (ARRAY[('draft'::character varying)::text, ('active'::character varying)::text, ('paused'::character varying)::text, ('closed'::character varying)::text])))
);


--
-- Name: job_positions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.job_positions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: job_positions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.job_positions_id_seq OWNED BY public.job_positions.id;


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: key_result_updates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.key_result_updates (
    id bigint NOT NULL,
    key_result_id bigint NOT NULL,
    user_id bigint NOT NULL,
    previous_value numeric(15,2) NOT NULL,
    new_value numeric(15,2) NOT NULL,
    note text,
    confidence character varying(64) DEFAULT 'medium'::character varying NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT key_result_updates_confidence_check CHECK (((confidence)::text = ANY (ARRAY[('low'::character varying)::text, ('medium'::character varying)::text, ('high'::character varying)::text])))
);


--
-- Name: key_result_updates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.key_result_updates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: key_result_updates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.key_result_updates_id_seq OWNED BY public.key_result_updates.id;


--
-- Name: key_results; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.key_results (
    id bigint NOT NULL,
    objective_id bigint NOT NULL,
    owner_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    metric_type character varying(64) DEFAULT 'percentage'::character varying NOT NULL,
    start_value numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    target_value numeric(15,2) NOT NULL,
    current_value numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    progress numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    weight numeric(5,2) DEFAULT '100'::numeric NOT NULL,
    status character varying(64) DEFAULT 'not_started'::character varying NOT NULL,
    due_date date,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT key_results_metric_type_check CHECK (((metric_type)::text = ANY (ARRAY[('number'::character varying)::text, ('percentage'::character varying)::text, ('currency'::character varying)::text, ('boolean'::character varying)::text, ('milestone'::character varying)::text]))),
    CONSTRAINT key_results_status_check CHECK (((status)::text = ANY (ARRAY[('not_started'::character varying)::text, ('on_track'::character varying)::text, ('at_risk'::character varying)::text, ('behind'::character varying)::text, ('completed'::character varying)::text])))
);


--
-- Name: key_results_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.key_results_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: key_results_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.key_results_id_seq OWNED BY public.key_results.id;


--
-- Name: learning_path_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.learning_path_items (
    id bigint NOT NULL,
    learning_path_id bigint NOT NULL,
    training_id bigint NOT NULL,
    order_number integer NOT NULL,
    is_required boolean DEFAULT true NOT NULL,
    prerequisite_item_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: learning_path_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.learning_path_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: learning_path_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.learning_path_items_id_seq OWNED BY public.learning_path_items.id;


--
-- Name: learning_paths; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.learning_paths (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    thumbnail_path character varying(255),
    level character varying(64) DEFAULT 'beginner'::character varying NOT NULL,
    estimated_hours integer DEFAULT 0 NOT NULL,
    is_mandatory boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT learning_paths_level_check CHECK (((level)::text = ANY (ARRAY[('beginner'::character varying)::text, ('intermediate'::character varying)::text, ('advanced'::character varying)::text])))
);


--
-- Name: learning_paths_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.learning_paths_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: learning_paths_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.learning_paths_id_seq OWNED BY public.learning_paths.id;


--
-- Name: leave_balances; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.leave_balances (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    user_id bigint NOT NULL,
    leave_type_id bigint NOT NULL,
    year integer NOT NULL,
    total_days numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    used_days numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    pending_days numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    carried_over numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    accrued numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    encashed numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    expired numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    carryover_expiry date
);


--
-- Name: leave_balances_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.leave_balances_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: leave_balances_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.leave_balances_id_seq OWNED BY public.leave_balances.id;


--
-- Name: leave_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.leave_requests (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    user_id bigint NOT NULL,
    leave_type_id bigint NOT NULL,
    start_date date NOT NULL,
    end_date date NOT NULL,
    total_days numeric(5,2) NOT NULL,
    reason text,
    status character varying(64) DEFAULT 'pending'::character varying NOT NULL,
    document_path character varying(255),
    document_name character varying(255),
    approved_by bigint,
    approved_at timestamp with time zone,
    approval_note text,
    rejected_by bigint,
    rejected_at timestamp with time zone,
    rejection_reason text,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    approval_workflow_id bigint,
    current_step integer,
    workflow_status character varying(64) DEFAULT 'pending'::character varying NOT NULL,
    custom_fields jsonb,
    CONSTRAINT leave_requests_status_check CHECK (((status)::text = ANY (ARRAY[('pending'::character varying)::text, ('approved'::character varying)::text, ('rejected'::character varying)::text, ('cancelled'::character varying)::text]))),
    CONSTRAINT leave_requests_workflow_status_check CHECK (((workflow_status)::text = ANY (ARRAY[('pending'::character varying)::text, ('in_progress'::character varying)::text, ('completed'::character varying)::text, ('rejected'::character varying)::text])))
);


--
-- Name: leave_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.leave_requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: leave_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.leave_requests_id_seq OWNED BY public.leave_requests.id;


--
-- Name: leave_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.leave_types (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(255),
    description text,
    is_paid boolean DEFAULT true NOT NULL,
    default_days integer DEFAULT 0 NOT NULL,
    requires_document boolean DEFAULT false NOT NULL,
    gender_restriction character varying(64) DEFAULT 'all'::character varying NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    max_days_at_once integer,
    min_days_notice integer DEFAULT 0 NOT NULL,
    approval_flow jsonb,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    approval_workflow_id bigint,
    accrual_policy_id bigint,
    system_code character varying(64),
    is_system boolean DEFAULT false NOT NULL,
    deducts_from_annual boolean DEFAULT false NOT NULL,
    CONSTRAINT leave_types_gender_restriction_check CHECK (((gender_restriction)::text = ANY (ARRAY[('all'::character varying)::text, ('male'::character varying)::text, ('female'::character varying)::text])))
);


--
-- Name: leave_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.leave_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: leave_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.leave_types_id_seq OWNED BY public.leave_types.id;


--
-- Name: legal_holds; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.legal_holds (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    subject_type character varying(32) NOT NULL,
    subject_id bigint NOT NULL,
    reason text NOT NULL,
    case_reference character varying(255),
    placed_by bigint NOT NULL,
    placed_at timestamp with time zone NOT NULL,
    released_at timestamp with time zone,
    released_by bigint,
    active boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: legal_holds_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.legal_holds_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: legal_holds_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.legal_holds_id_seq OWNED BY public.legal_holds.id;


--
-- Name: license_package_modules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.license_package_modules (
    id bigint NOT NULL,
    license_package_id bigint NOT NULL,
    module_id bigint NOT NULL,
    is_included boolean DEFAULT true NOT NULL,
    additional_price numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: license_package_modules_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.license_package_modules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: license_package_modules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.license_package_modules_id_seq OWNED BY public.license_package_modules.id;


--
-- Name: license_packages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.license_packages (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    description text,
    base_price numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    annual_price numeric(10,2),
    user_limit integer DEFAULT 5 NOT NULL,
    location_limit integer DEFAULT 1 NOT NULL,
    employee_limit integer DEFAULT 50 NOT NULL,
    storage_limit_gb integer DEFAULT 5 NOT NULL,
    duration_months integer DEFAULT 12 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    is_featured boolean DEFAULT false NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    settings jsonb,
    features jsonb,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: license_packages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.license_packages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: license_packages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.license_packages_id_seq OWNED BY public.license_packages.id;


--
-- Name: lookups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.lookups (
    id bigint NOT NULL,
    company_id bigint,
    lookup_type character varying(64) NOT NULL,
    value character varying(100) NOT NULL,
    label character varying(255) NOT NULL,
    color character varying(32),
    sort_order integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    is_system boolean DEFAULT false NOT NULL,
    parent_lookup_id bigint,
    meta jsonb,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: lookups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.lookups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: lookups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.lookups_id_seq OWNED BY public.lookups.id;


--
-- Name: mandatory_trainings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mandatory_trainings (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    training_id bigint NOT NULL,
    scope character varying(64) DEFAULT 'all'::character varying NOT NULL,
    scope_value character varying(255),
    completion_days integer DEFAULT 30 NOT NULL,
    recertification_months integer,
    is_active boolean DEFAULT true NOT NULL,
    created_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT mandatory_trainings_scope_check CHECK (((scope)::text = ANY (ARRAY[('all'::character varying)::text, ('department'::character varying)::text, ('position'::character varying)::text, ('new_hires'::character varying)::text])))
);


--
-- Name: mandatory_trainings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.mandatory_trainings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mandatory_trainings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mandatory_trainings_id_seq OWNED BY public.mandatory_trainings.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: milestone_completions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.milestone_completions (
    id bigint NOT NULL,
    onboarding_process_id bigint NOT NULL,
    onboarding_milestone_id bigint NOT NULL,
    user_id bigint NOT NULL,
    status character varying(64) DEFAULT 'pending'::character varying NOT NULL,
    due_date date NOT NULL,
    completed_at timestamp with time zone,
    checklist_responses jsonb,
    evaluation_scores jsonb,
    employee_feedback text,
    manager_feedback text,
    overall_rating integer,
    completed_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT milestone_completions_status_check CHECK (((status)::text = ANY (ARRAY[('pending'::character varying)::text, ('scheduled'::character varying)::text, ('completed'::character varying)::text, ('skipped'::character varying)::text])))
);


--
-- Name: milestone_completions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.milestone_completions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: milestone_completions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.milestone_completions_id_seq OWNED BY public.milestone_completions.id;


--
-- Name: model_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_permissions (
    permission_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


--
-- Name: model_has_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_roles (
    role_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


--
-- Name: modules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.modules (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    description text,
    icon character varying(255),
    is_core boolean DEFAULT false NOT NULL,
    price_monthly numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    price_yearly numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: modules_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.modules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: modules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.modules_id_seq OWNED BY public.modules.id;


--
-- Name: notification_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notification_templates (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    event_key character varying(100) NOT NULL,
    subject character varying(255) NOT NULL,
    body text NOT NULL,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: notification_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notification_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notification_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notification_templates_id_seq OWNED BY public.notification_templates.id;


--
-- Name: notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notifications (
    id uuid NOT NULL,
    company_id bigint,
    type character varying(255) NOT NULL,
    notifiable_type character varying(255) NOT NULL,
    notifiable_id bigint NOT NULL,
    data text NOT NULL,
    read_at timestamp with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: objectives; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.objectives (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    performance_period_id bigint,
    parent_id bigint,
    level character varying(64) DEFAULT 'individual'::character varying NOT NULL,
    department_id bigint,
    owner_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    start_date date NOT NULL,
    end_date date NOT NULL,
    progress numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    status character varying(64) DEFAULT 'draft'::character varying NOT NULL,
    weight numeric(5,2) DEFAULT '100'::numeric NOT NULL,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT objectives_level_check CHECK (((level)::text = ANY (ARRAY[('company'::character varying)::text, ('department'::character varying)::text, ('team'::character varying)::text, ('individual'::character varying)::text]))),
    CONSTRAINT objectives_status_check CHECK (((status)::text = ANY (ARRAY[('draft'::character varying)::text, ('active'::character varying)::text, ('completed'::character varying)::text, ('cancelled'::character varying)::text])))
);


--
-- Name: objectives_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.objectives_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: objectives_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.objectives_id_seq OWNED BY public.objectives.id;


--
-- Name: onboarding_milestones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.onboarding_milestones (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    onboarding_template_id bigint,
    name character varying(255) NOT NULL,
    day_number integer NOT NULL,
    description text,
    checklist jsonb,
    evaluation_criteria jsonb,
    requires_meeting boolean DEFAULT true NOT NULL,
    requires_feedback boolean DEFAULT true NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: onboarding_milestones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.onboarding_milestones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: onboarding_milestones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.onboarding_milestones_id_seq OWNED BY public.onboarding_milestones.id;


--
-- Name: onboarding_processes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.onboarding_processes (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    user_id bigint NOT NULL,
    template_id bigint,
    title character varying(255) NOT NULL,
    start_date date NOT NULL,
    target_end_date date,
    actual_end_date date,
    status character varying(64) DEFAULT 'pending'::character varying NOT NULL,
    progress integer DEFAULT 0 NOT NULL,
    notes text,
    assigned_to bigint,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    is_preboarding_enabled boolean DEFAULT false NOT NULL,
    preboarding_started_at timestamp with time zone,
    first_day timestamp with time zone,
    buddy_id bigint,
    process_type character varying(32) DEFAULT 'onboarding'::character varying NOT NULL,
    termination_reason_code character varying(10),
    termination_date date,
    exit_notes text,
    remaining_leave_days numeric(8,2),
    employee_id bigint,
    CONSTRAINT onboarding_processes_process_type_check CHECK (((process_type)::text = ANY (ARRAY[('onboarding'::character varying)::text, ('offboarding'::character varying)::text]))),
    CONSTRAINT onboarding_processes_status_check CHECK (((status)::text = ANY (ARRAY[('pending'::character varying)::text, ('in_progress'::character varying)::text, ('completed'::character varying)::text, ('cancelled'::character varying)::text])))
);


--
-- Name: onboarding_processes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.onboarding_processes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: onboarding_processes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.onboarding_processes_id_seq OWNED BY public.onboarding_processes.id;


--
-- Name: onboarding_surveys; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.onboarding_surveys (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    onboarding_process_id bigint NOT NULL,
    user_id bigint NOT NULL,
    survey_type character varying(64) DEFAULT 'month_3'::character varying NOT NULL,
    nps_score integer,
    responses jsonb,
    additional_comments text,
    sent_at timestamp with time zone,
    completed_at timestamp with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT onboarding_surveys_survey_type_check CHECK (((survey_type)::text = ANY (ARRAY[('week_1'::character varying)::text, ('week_4'::character varying)::text, ('month_3'::character varying)::text, ('exit'::character varying)::text])))
);


--
-- Name: onboarding_surveys_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.onboarding_surveys_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: onboarding_surveys_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.onboarding_surveys_id_seq OWNED BY public.onboarding_surveys.id;


--
-- Name: onboarding_tasks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.onboarding_tasks (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    process_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    type character varying(64) DEFAULT 'custom'::character varying NOT NULL,
    "order" integer DEFAULT 0 NOT NULL,
    is_required boolean DEFAULT true NOT NULL,
    due_date date,
    status character varying(64) DEFAULT 'pending'::character varying NOT NULL,
    completed_at timestamp with time zone,
    completed_by bigint,
    data jsonb,
    notes text,
    assigned_to bigint,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT onboarding_tasks_status_check CHECK (((status)::text = ANY (ARRAY[('pending'::character varying)::text, ('in_progress'::character varying)::text, ('completed'::character varying)::text, ('skipped'::character varying)::text]))),
    CONSTRAINT onboarding_tasks_type_check CHECK (((type)::text = ANY (ARRAY[('document_upload'::character varying)::text, ('document_fill'::character varying)::text, ('training'::character varying)::text, ('meeting'::character varying)::text, ('system_setup'::character varying)::text, ('quiz'::character varying)::text, ('custom'::character varying)::text])))
);


--
-- Name: onboarding_tasks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.onboarding_tasks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: onboarding_tasks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.onboarding_tasks_id_seq OWNED BY public.onboarding_tasks.id;


--
-- Name: onboarding_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.onboarding_templates (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    tasks jsonb NOT NULL,
    estimated_days integer DEFAULT 7 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    process_type character varying(32) DEFAULT 'onboarding'::character varying NOT NULL,
    CONSTRAINT onboarding_templates_process_type_check CHECK (((process_type)::text = ANY (ARRAY[('onboarding'::character varying)::text, ('offboarding'::character varying)::text])))
);


--
-- Name: onboarding_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.onboarding_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: onboarding_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.onboarding_templates_id_seq OWNED BY public.onboarding_templates.id;


--
-- Name: one_on_one_meetings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.one_on_one_meetings (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    manager_id bigint NOT NULL,
    employee_id bigint NOT NULL,
    scheduled_at timestamp with time zone NOT NULL,
    completed_at timestamp with time zone,
    duration_minutes integer,
    location character varying(255),
    meeting_link character varying(255),
    status character varying(64) DEFAULT 'scheduled'::character varying NOT NULL,
    agenda text,
    notes text,
    action_items jsonb,
    talking_points jsonb,
    mood character varying(64),
    created_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT one_on_one_meetings_mood_check CHECK (((mood)::text = ANY (ARRAY[('very_negative'::character varying)::text, ('negative'::character varying)::text, ('neutral'::character varying)::text, ('positive'::character varying)::text, ('very_positive'::character varying)::text]))),
    CONSTRAINT one_on_one_meetings_status_check CHECK (((status)::text = ANY (ARRAY[('scheduled'::character varying)::text, ('completed'::character varying)::text, ('cancelled'::character varying)::text, ('rescheduled'::character varying)::text])))
);


--
-- Name: one_on_one_meetings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.one_on_one_meetings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: one_on_one_meetings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.one_on_one_meetings_id_seq OWNED BY public.one_on_one_meetings.id;


--
-- Name: organizations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.organizations (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    settings jsonb,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: organizations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.organizations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: organizations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.organizations_id_seq OWNED BY public.organizations.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp with time zone
);


--
-- Name: payslips; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payslips (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    employee_id bigint NOT NULL,
    period character varying(255) NOT NULL,
    year integer NOT NULL,
    month integer NOT NULL,
    gross_salary numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    net_salary numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    deductions jsonb,
    bonuses jsonb,
    total_deductions numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    total_bonuses numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    worked_days integer,
    overtime_hours numeric(8,2),
    file_path character varying(255),
    is_published boolean DEFAULT false NOT NULL,
    published_at timestamp with time zone,
    published_by bigint,
    is_viewed boolean DEFAULT false NOT NULL,
    viewed_at timestamp with time zone,
    notes text,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: payslips_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payslips_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payslips_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payslips_id_seq OWNED BY public.payslips.id;


--
-- Name: performance_criteria; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.performance_criteria (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    weight integer DEFAULT 1 NOT NULL,
    max_score integer DEFAULT 5 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: performance_criteria_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.performance_criteria_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: performance_criteria_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.performance_criteria_id_seq OWNED BY public.performance_criteria.id;


--
-- Name: performance_periods; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.performance_periods (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    start_date date NOT NULL,
    end_date date NOT NULL,
    status character varying(64) DEFAULT 'draft'::character varying NOT NULL,
    description text,
    created_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT performance_periods_status_check CHECK (((status)::text = ANY (ARRAY[('draft'::character varying)::text, ('active'::character varying)::text, ('closed'::character varying)::text])))
);


--
-- Name: performance_periods_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.performance_periods_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: performance_periods_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.performance_periods_id_seq OWNED BY public.performance_periods.id;


--
-- Name: performance_reviews; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.performance_reviews (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    period_id bigint NOT NULL,
    employee_id bigint NOT NULL,
    reviewer_id bigint NOT NULL,
    status character varying(64) DEFAULT 'draft'::character varying NOT NULL,
    overall_score numeric(5,2),
    strengths text,
    improvements text,
    goals text,
    reviewer_comments text,
    employee_comments text,
    submitted_at timestamp with time zone,
    approved_at timestamp with time zone,
    approved_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    is_360_enabled boolean DEFAULT false NOT NULL,
    self_score numeric(5,2),
    manager_score numeric(5,2),
    peer_score numeric(5,2),
    report_score numeric(5,2),
    final_score numeric(5,2),
    CONSTRAINT performance_reviews_status_check CHECK (((status)::text = ANY (ARRAY[('draft'::character varying)::text, ('submitted'::character varying)::text, ('approved'::character varying)::text, ('rejected'::character varying)::text])))
);


--
-- Name: performance_reviews_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.performance_reviews_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: performance_reviews_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.performance_reviews_id_seq OWNED BY public.performance_reviews.id;


--
-- Name: performance_scores; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.performance_scores (
    id bigint NOT NULL,
    review_id bigint NOT NULL,
    criteria_id bigint NOT NULL,
    score integer NOT NULL,
    comment text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: performance_scores_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.performance_scores_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: performance_scores_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.performance_scores_id_seq OWNED BY public.performance_scores.id;


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissions (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name text NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp with time zone,
    expires_at timestamp with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: position_competencies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.position_competencies (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    position_name character varying(255) NOT NULL,
    competency_id bigint NOT NULL,
    expected_level integer NOT NULL,
    weight numeric(5,2) DEFAULT '100'::numeric NOT NULL,
    is_required boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: position_competencies_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.position_competencies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: position_competencies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.position_competencies_id_seq OWNED BY public.position_competencies.id;


--
-- Name: positions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.positions (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    code character varying(50) NOT NULL,
    name character varying(255) NOT NULL,
    department_id bigint,
    sgk_occupation_code character varying(20),
    description text,
    is_active boolean DEFAULT true NOT NULL,
    is_system boolean DEFAULT false NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: positions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.positions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: positions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.positions_id_seq OWNED BY public.positions.id;


--
-- Name: preboarding_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.preboarding_tokens (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    onboarding_process_id bigint NOT NULL,
    user_id bigint,
    token character varying(64) NOT NULL,
    email character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    expires_at timestamp with time zone NOT NULL,
    used_at timestamp with time zone,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: preboarding_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.preboarding_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: preboarding_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.preboarding_tokens_id_seq OWNED BY public.preboarding_tokens.id;


--
-- Name: privacy_notices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.privacy_notices (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    audience character varying(32) NOT NULL,
    version integer NOT NULL,
    title character varying(255) NOT NULL,
    body text NOT NULL,
    effective_from timestamp with time zone,
    is_active boolean DEFAULT false NOT NULL,
    published_at timestamp with time zone,
    published_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT privacy_notices_audience_check CHECK (((audience)::text = ANY (ARRAY[('employee'::character varying)::text, ('candidate'::character varying)::text, ('visitor'::character varying)::text, ('contractor'::character varying)::text])))
);


--
-- Name: privacy_notices_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.privacy_notices_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: privacy_notices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.privacy_notices_id_seq OWNED BY public.privacy_notices.id;


--
-- Name: report_access_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.report_access_logs (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    user_id bigint NOT NULL,
    report_id bigint,
    dashboard_id bigint,
    action character varying(32) NOT NULL,
    dataset_key character varying(64),
    row_count integer DEFAULT 0 NOT NULL,
    contains_sensitive boolean DEFAULT false NOT NULL,
    sensitive_fields jsonb,
    filters_hash character varying(64),
    filter_field_keys jsonb,
    duration_ms integer,
    ip character varying(45),
    user_agent text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT report_access_logs_action_check CHECK (((action)::text = ANY (ARRAY[('run'::character varying)::text, ('preview'::character varying)::text, ('export'::character varying)::text, ('drill_details'::character varying)::text, ('scheduled'::character varying)::text]))),
    CONSTRAINT report_access_logs_target_check CHECK ((((report_id IS NOT NULL) AND (dashboard_id IS NULL)) OR ((report_id IS NULL) AND (dashboard_id IS NOT NULL)) OR ((report_id IS NULL) AND (dashboard_id IS NULL))))
);


--
-- Name: report_access_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.report_access_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: report_access_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.report_access_logs_id_seq OWNED BY public.report_access_logs.id;


--
-- Name: report_folders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.report_folders (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    parent_id bigint,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: report_folders_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.report_folders_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: report_folders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.report_folders_id_seq OWNED BY public.report_folders.id;


--
-- Name: report_measures; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.report_measures (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    dataset_key character varying(64) NOT NULL,
    key character varying(64) NOT NULL,
    label character varying(255) NOT NULL,
    expression text NOT NULL,
    format character varying(32) DEFAULT 'number'::character varying NOT NULL,
    decimals smallint DEFAULT '2'::smallint NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT report_measures_format_check CHECK (((format)::text = ANY (ARRAY[('number'::character varying)::text, ('money'::character varying)::text, ('percent'::character varying)::text])))
);


--
-- Name: report_measures_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.report_measures_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: report_measures_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.report_measures_id_seq OWNED BY public.report_measures.id;


--
-- Name: report_schedules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.report_schedules (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    report_id bigint,
    dashboard_id bigint,
    owner_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    cadence character varying(16) NOT NULL,
    hour smallint DEFAULT '8'::smallint NOT NULL,
    minute smallint DEFAULT '0'::smallint NOT NULL,
    day smallint,
    cron_expression character varying(64),
    timezone character varying(64) DEFAULT 'Europe/Istanbul'::character varying NOT NULL,
    format character varying(16) DEFAULT 'link'::character varying NOT NULL,
    recipients jsonb DEFAULT '[]'::jsonb NOT NULL,
    filters jsonb,
    only_if_data boolean DEFAULT false NOT NULL,
    active boolean DEFAULT true NOT NULL,
    last_run_at timestamp(0) with time zone,
    last_status character varying(32),
    failure_count integer DEFAULT 0 NOT NULL,
    next_run_at timestamp(0) with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT report_schedules_cadence_check CHECK (((cadence)::text = ANY (ARRAY[('daily'::character varying)::text, ('weekly'::character varying)::text, ('monthly'::character varying)::text, ('cron'::character varying)::text]))),
    CONSTRAINT report_schedules_format_check CHECK (((format)::text = ANY (ARRAY[('link'::character varying)::text, ('excel'::character varying)::text, ('pdf'::character varying)::text]))),
    CONSTRAINT report_schedules_target_check CHECK ((((report_id IS NOT NULL) AND (dashboard_id IS NULL)) OR ((report_id IS NULL) AND (dashboard_id IS NOT NULL))))
);


--
-- Name: report_schedules_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.report_schedules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: report_schedules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.report_schedules_id_seq OWNED BY public.report_schedules.id;


--
-- Name: report_shares; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.report_shares (
    id bigint NOT NULL,
    saved_report_id bigint NOT NULL,
    company_id bigint NOT NULL,
    user_id bigint,
    role_id bigint,
    department_id bigint,
    level character varying(16) DEFAULT 'viewer'::character varying NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT report_shares_level_check CHECK (((level)::text = ANY (ARRAY[('viewer'::character varying)::text, ('editor'::character varying)::text]))),
    CONSTRAINT report_shares_target_check CHECK ((((user_id IS NOT NULL) AND (role_id IS NULL) AND (department_id IS NULL)) OR ((user_id IS NULL) AND (role_id IS NOT NULL) AND (department_id IS NULL)) OR ((user_id IS NULL) AND (role_id IS NULL) AND (department_id IS NOT NULL))))
);


--
-- Name: report_shares_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.report_shares_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: report_shares_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.report_shares_id_seq OWNED BY public.report_shares.id;


--
-- Name: request_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.request_types (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    description text,
    icon character varying(255),
    color character varying(255),
    requires_approval boolean DEFAULT true NOT NULL,
    requires_attachment boolean DEFAULT false NOT NULL,
    approval_flow jsonb,
    form_fields jsonb,
    is_active boolean DEFAULT true NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: request_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.request_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: request_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.request_types_id_seq OWNED BY public.request_types.id;


--
-- Name: required_documents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.required_documents (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    document_category_id bigint,
    name character varying(255) NOT NULL,
    description text,
    scope character varying(64) DEFAULT 'all'::character varying NOT NULL,
    scope_value character varying(255),
    is_mandatory boolean DEFAULT true NOT NULL,
    validity_months integer,
    reminder_days_before integer DEFAULT 30 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT required_documents_scope_check CHECK (((scope)::text = ANY (ARRAY[('all'::character varying)::text, ('department'::character varying)::text, ('position'::character varying)::text, ('employee_type'::character varying)::text])))
);


--
-- Name: required_documents_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.required_documents_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: required_documents_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.required_documents_id_seq OWNED BY public.required_documents.id;


--
-- Name: retention_decisions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.retention_decisions (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    destruction_candidate_id bigint,
    subject_type character varying(32) NOT NULL,
    subject_id bigint NOT NULL,
    decision character varying(32) NOT NULL,
    reason text NOT NULL,
    defer_until date,
    decided_by bigint NOT NULL,
    created_at timestamp with time zone NOT NULL,
    CONSTRAINT retention_decisions_decision_check CHECK (((decision)::text = ANY (ARRAY[('destroy'::character varying)::text, ('defer'::character varying)::text, ('exclude'::character varying)::text])))
);


--
-- Name: retention_decisions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.retention_decisions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: retention_decisions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.retention_decisions_id_seq OWNED BY public.retention_decisions.id;


--
-- Name: retention_policies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.retention_policies (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    data_category character varying(64) NOT NULL,
    subject_type character varying(32) NOT NULL,
    trigger_event character varying(32) NOT NULL,
    retention_months integer NOT NULL,
    strategy character varying(32) DEFAULT 'anonymize'::character varying NOT NULL,
    legal_basis_note text,
    active boolean DEFAULT false NOT NULL,
    requires_approval boolean DEFAULT true NOT NULL,
    is_system_draft boolean DEFAULT false NOT NULL,
    name character varying(255) NOT NULL,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT retention_policies_strategy_check CHECK (((strategy)::text = ANY (ARRAY[('anonymize'::character varying)::text, ('pseudonymize'::character varying)::text, ('hard_delete'::character varying)::text, ('archive'::character varying)::text]))),
    CONSTRAINT retention_policies_trigger_check CHECK (((trigger_event)::text = ANY (ARRAY[('ise_giris'::character varying)::text, ('isten_ayrilma'::character varying)::text, ('basvuru_reddi'::character varying)::text, ('kayit_tarihi'::character varying)::text, ('belge_tarihi'::character varying)::text, ('son_islem_tarihi'::character varying)::text])))
);


--
-- Name: retention_policies_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.retention_policies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: retention_policies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.retention_policies_id_seq OWNED BY public.retention_policies.id;


--
-- Name: role_default_dashboards; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.role_default_dashboards (
    id bigint NOT NULL,
    role_key character varying(64) NOT NULL,
    dashboard_system_key character varying(128) NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: role_default_dashboards_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.role_default_dashboards_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: role_default_dashboards_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.role_default_dashboards_id_seq OWNED BY public.role_default_dashboards.id;


--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.role_has_permissions (
    permission_id bigint NOT NULL,
    role_id bigint NOT NULL
);


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    data_scope character varying(32),
    panel_access boolean DEFAULT true NOT NULL,
    CONSTRAINT roles_data_scope_check CHECK (((data_scope)::text = ANY (ARRAY[('own'::character varying)::text, ('team'::character varying)::text, ('department'::character varying)::text, ('branch'::character varying)::text, ('company'::character varying)::text])))
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: salary_bands; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.salary_bands (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    position_id bigint NOT NULL,
    min_amount numeric(12,2) NOT NULL,
    mid_amount numeric(12,2) NOT NULL,
    max_amount numeric(12,2) NOT NULL,
    currency character varying(3) DEFAULT 'TRY'::character varying NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT salary_bands_order_check CHECK (((min_amount <= mid_amount) AND (mid_amount <= max_amount)))
);


--
-- Name: salary_bands_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.salary_bands_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: salary_bands_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.salary_bands_id_seq OWNED BY public.salary_bands.id;


--
-- Name: salary_records; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.salary_records (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    employee_id bigint NOT NULL,
    effective_date date NOT NULL,
    amount numeric(12,2) NOT NULL,
    currency character varying(3) DEFAULT 'TRY'::character varying NOT NULL,
    change_reason character varying(64) NOT NULL,
    note text,
    created_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    updated_by bigint,
    CONSTRAINT salary_records_amount_positive CHECK ((amount >= (0)::numeric))
);


--
-- Name: salary_records_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.salary_records_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: salary_records_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.salary_records_id_seq OWNED BY public.salary_records.id;


--
-- Name: salary_review_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.salary_review_items (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    period_id bigint NOT NULL,
    employee_id bigint NOT NULL,
    current_amount numeric(12,2),
    proposed_amount numeric(12,2) NOT NULL,
    increase_percent numeric(8,2),
    currency character varying(3) DEFAULT 'TRY'::character varying NOT NULL,
    change_reason character varying(64) DEFAULT 'annual_raise'::character varying NOT NULL,
    note text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: salary_review_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.salary_review_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: salary_review_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.salary_review_items_id_seq OWNED BY public.salary_review_items.id;


--
-- Name: salary_review_periods; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.salary_review_periods (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    scope_type character varying(32) DEFAULT 'company'::character varying NOT NULL,
    scope_id bigint,
    effective_date date NOT NULL,
    status character varying(32) DEFAULT 'draft'::character varying NOT NULL,
    notes text,
    created_by bigint,
    updated_by bigint,
    submitted_by bigint,
    submitted_at timestamp with time zone,
    approved_by bigint,
    approved_at timestamp with time zone,
    rejection_reason text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT salary_review_periods_scope_check CHECK (((scope_type)::text = ANY (ARRAY[('company'::character varying)::text, ('department'::character varying)::text, ('branch'::character varying)::text]))),
    CONSTRAINT salary_review_periods_status_check CHECK (((status)::text = ANY (ARRAY[('draft'::character varying)::text, ('pending_approval'::character varying)::text, ('approved'::character varying)::text, ('rejected'::character varying)::text, ('cancelled'::character varying)::text])))
);


--
-- Name: salary_review_periods_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.salary_review_periods_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: salary_review_periods_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.salary_review_periods_id_seq OWNED BY public.salary_review_periods.id;


--
-- Name: saved_reports; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.saved_reports (
    id bigint NOT NULL,
    company_id bigint,
    user_id bigint,
    name character varying(255) NOT NULL,
    description text,
    config jsonb NOT NULL,
    is_favorite boolean DEFAULT false NOT NULL,
    is_shared boolean DEFAULT false NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    dataset_key character varying(64),
    share_user_ids jsonb,
    share_role_ids jsonb,
    is_system boolean DEFAULT false NOT NULL,
    folder_id bigint,
    cache_ttl_seconds integer,
    module_key character varying(64),
    system_key character varying(128)
);


--
-- Name: saved_reports_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.saved_reports_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: saved_reports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.saved_reports_id_seq OWNED BY public.saved_reports.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: setting_values; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.setting_values (
    id bigint NOT NULL,
    company_id bigint,
    scope_type character varying(32) NOT NULL,
    scope_id bigint,
    key character varying(191) NOT NULL,
    value jsonb NOT NULL,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: setting_values_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.setting_values_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: setting_values_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.setting_values_id_seq OWNED BY public.setting_values.id;


--
-- Name: shifts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.shifts (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(255),
    start_time time(0) without time zone NOT NULL,
    end_time time(0) without time zone NOT NULL,
    break_start time(0) without time zone,
    break_end time(0) without time zone,
    break_duration_minutes integer DEFAULT 0 NOT NULL,
    color character varying(255) DEFAULT '#3b82f6'::character varying NOT NULL,
    is_night_shift boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: shifts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.shifts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: shifts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.shifts_id_seq OWNED BY public.shifts.id;


--
-- Name: software_license_assignments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.software_license_assignments (
    id bigint NOT NULL,
    software_license_id bigint NOT NULL,
    user_id bigint NOT NULL,
    assigned_at date NOT NULL,
    revoked_at date,
    is_active boolean DEFAULT true NOT NULL,
    assigned_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: software_license_assignments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.software_license_assignments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: software_license_assignments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.software_license_assignments_id_seq OWNED BY public.software_license_assignments.id;


--
-- Name: software_licenses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.software_licenses (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    vendor character varying(255) NOT NULL,
    version character varying(255),
    license_type character varying(64) DEFAULT 'subscription'::character varying NOT NULL,
    total_seats integer,
    used_seats integer DEFAULT 0 NOT NULL,
    purchase_date date,
    expiry_date date,
    purchase_cost numeric(15,2),
    annual_cost numeric(15,2),
    currency character varying(3) DEFAULT 'TRY'::character varying NOT NULL,
    license_key character varying(255),
    notes text,
    is_active boolean DEFAULT true NOT NULL,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT software_licenses_license_type_check CHECK (((license_type)::text = ANY (ARRAY[('perpetual'::character varying)::text, ('subscription'::character varying)::text, ('per_seat'::character varying)::text, ('concurrent'::character varying)::text, ('site'::character varying)::text, ('open_source'::character varying)::text])))
);


--
-- Name: software_licenses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.software_licenses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: software_licenses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.software_licenses_id_seq OWNED BY public.software_licenses.id;


--
-- Name: survey_questions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.survey_questions (
    id bigint NOT NULL,
    survey_id bigint NOT NULL,
    order_number integer NOT NULL,
    question_text text NOT NULL,
    question_type character varying(64) NOT NULL,
    options jsonb,
    min_value integer,
    max_value integer,
    is_required boolean DEFAULT true NOT NULL,
    category character varying(255),
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT survey_questions_question_type_check CHECK (((question_type)::text = ANY (ARRAY[('single_choice'::character varying)::text, ('multiple_choice'::character varying)::text, ('rating'::character varying)::text, ('nps'::character varying)::text, ('text'::character varying)::text, ('scale'::character varying)::text, ('matrix'::character varying)::text])))
);


--
-- Name: survey_questions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.survey_questions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: survey_questions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.survey_questions_id_seq OWNED BY public.survey_questions.id;


--
-- Name: survey_responses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.survey_responses (
    id bigint NOT NULL,
    survey_submission_id bigint NOT NULL,
    survey_question_id bigint NOT NULL,
    answer_text text,
    answer_numeric integer,
    answer_array jsonb,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: survey_responses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.survey_responses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: survey_responses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.survey_responses_id_seq OWNED BY public.survey_responses.id;


--
-- Name: survey_submissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.survey_submissions (
    id bigint NOT NULL,
    survey_id bigint NOT NULL,
    user_id bigint,
    anonymous_id character varying(255),
    status character varying(64) DEFAULT 'started'::character varying NOT NULL,
    started_at timestamp with time zone NOT NULL,
    completed_at timestamp with time zone,
    ip_address character varying(255),
    user_agent character varying(255),
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT survey_submissions_status_check CHECK (((status)::text = ANY (ARRAY[('started'::character varying)::text, ('completed'::character varying)::text, ('abandoned'::character varying)::text])))
);


--
-- Name: survey_submissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.survey_submissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: survey_submissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.survey_submissions_id_seq OWNED BY public.survey_submissions.id;


--
-- Name: surveys; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.surveys (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    type character varying(64) DEFAULT 'custom'::character varying NOT NULL,
    is_anonymous boolean DEFAULT true NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    start_date timestamp with time zone,
    end_date timestamp with time zone,
    recurrence character varying(64) DEFAULT 'none'::character varying NOT NULL,
    audience character varying(64) DEFAULT 'all'::character varying NOT NULL,
    audience_filter jsonb,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT surveys_audience_check CHECK (((audience)::text = ANY (ARRAY[('all'::character varying)::text, ('department'::character varying)::text, ('position'::character varying)::text, ('custom'::character varying)::text]))),
    CONSTRAINT surveys_recurrence_check CHECK (((recurrence)::text = ANY (ARRAY[('none'::character varying)::text, ('weekly'::character varying)::text, ('monthly'::character varying)::text, ('quarterly'::character varying)::text, ('yearly'::character varying)::text]))),
    CONSTRAINT surveys_type_check CHECK (((type)::text = ANY (ARRAY[('engagement'::character varying)::text, ('satisfaction'::character varying)::text, ('pulse'::character varying)::text, ('enps'::character varying)::text, ('onboarding'::character varying)::text, ('exit'::character varying)::text, ('custom'::character varying)::text])))
);


--
-- Name: surveys_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.surveys_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: surveys_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.surveys_id_seq OWNED BY public.surveys.id;


--
-- Name: telescope_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.telescope_entries (
    sequence bigint NOT NULL,
    uuid uuid NOT NULL,
    batch_id uuid NOT NULL,
    family_hash character varying(255),
    should_display_on_index boolean DEFAULT true NOT NULL,
    type character varying(20) NOT NULL,
    content text NOT NULL,
    created_at timestamp with time zone
);


--
-- Name: telescope_entries_sequence_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.telescope_entries_sequence_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: telescope_entries_sequence_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.telescope_entries_sequence_seq OWNED BY public.telescope_entries.sequence;


--
-- Name: telescope_entries_tags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.telescope_entries_tags (
    entry_uuid uuid NOT NULL,
    tag character varying(255) NOT NULL
);


--
-- Name: telescope_monitoring; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.telescope_monitoring (
    tag character varying(255) NOT NULL
);


--
-- Name: timesheets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.timesheets (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    user_id bigint NOT NULL,
    period_start date NOT NULL,
    period_end date NOT NULL,
    period_type character varying(255) DEFAULT 'weekly'::character varying NOT NULL,
    total_hours numeric(6,2) DEFAULT '0'::numeric NOT NULL,
    regular_hours numeric(6,2) DEFAULT '0'::numeric NOT NULL,
    overtime_hours numeric(6,2) DEFAULT '0'::numeric NOT NULL,
    working_days integer DEFAULT 0 NOT NULL,
    absent_days integer DEFAULT 0 NOT NULL,
    late_days integer DEFAULT 0 NOT NULL,
    leave_days integer DEFAULT 0 NOT NULL,
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    employee_notes text,
    manager_notes text,
    submitted_by bigint,
    submitted_at timestamp with time zone,
    approved_by bigint,
    approved_at timestamp with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: timesheets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.timesheets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: timesheets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.timesheets_id_seq OWNED BY public.timesheets.id;


--
-- Name: training_certificates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.training_certificates (
    id bigint NOT NULL,
    participant_id bigint NOT NULL,
    certificate_number character varying(255) NOT NULL,
    issue_date date NOT NULL,
    expiry_date date,
    file_path character varying(255),
    issued_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    is_valid boolean DEFAULT true NOT NULL,
    last_reminded_at date,
    external_certificate_id character varying(255),
    issuing_organization character varying(255)
);


--
-- Name: training_certificates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.training_certificates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: training_certificates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.training_certificates_id_seq OWNED BY public.training_certificates.id;


--
-- Name: training_participants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.training_participants (
    id bigint NOT NULL,
    session_id bigint NOT NULL,
    user_id bigint NOT NULL,
    status character varying(64) DEFAULT 'registered'::character varying NOT NULL,
    score integer,
    passed boolean,
    feedback text,
    registered_at timestamp with time zone,
    completed_at timestamp with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT training_participants_status_check CHECK (((status)::text = ANY (ARRAY[('registered'::character varying)::text, ('attended'::character varying)::text, ('absent'::character varying)::text, ('excused'::character varying)::text])))
);


--
-- Name: training_participants_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.training_participants_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: training_participants_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.training_participants_id_seq OWNED BY public.training_participants.id;


--
-- Name: training_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.training_requests (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    user_id bigint NOT NULL,
    training_name character varying(255) NOT NULL,
    description text,
    justification text NOT NULL,
    provider character varying(255),
    estimated_cost numeric(10,2),
    currency character varying(3) DEFAULT 'TRY'::character varying NOT NULL,
    status character varying(64) DEFAULT 'pending'::character varying NOT NULL,
    approval_notes text,
    approved_by bigint,
    approved_at timestamp with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    CONSTRAINT training_requests_status_check CHECK (((status)::text = ANY (ARRAY[('pending'::character varying)::text, ('approved'::character varying)::text, ('rejected'::character varying)::text, ('completed'::character varying)::text])))
);


--
-- Name: training_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.training_requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: training_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.training_requests_id_seq OWNED BY public.training_requests.id;


--
-- Name: training_sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.training_sessions (
    id bigint NOT NULL,
    training_id bigint NOT NULL,
    start_date timestamp with time zone NOT NULL,
    end_date timestamp with time zone NOT NULL,
    location character varying(255),
    instructor character varying(255),
    status character varying(64) DEFAULT 'scheduled'::character varying NOT NULL,
    notes text,
    created_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT training_sessions_status_check CHECK (((status)::text = ANY (ARRAY[('scheduled'::character varying)::text, ('in_progress'::character varying)::text, ('completed'::character varying)::text, ('cancelled'::character varying)::text])))
);


--
-- Name: training_sessions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.training_sessions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: training_sessions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.training_sessions_id_seq OWNED BY public.training_sessions.id;


--
-- Name: trainings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.trainings (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    category character varying(255),
    type character varying(64) DEFAULT 'classroom'::character varying NOT NULL,
    instructor character varying(255),
    location character varying(255),
    duration_hours integer,
    max_participants integer,
    cost numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    is_mandatory boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT trainings_type_check CHECK (((type)::text = ANY (ARRAY[('online'::character varying)::text, ('classroom'::character varying)::text, ('hybrid'::character varying)::text])))
);


--
-- Name: trainings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.trainings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: trainings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.trainings_id_seq OWNED BY public.trainings.id;


--
-- Name: user_competencies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_competencies (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    user_id bigint NOT NULL,
    competency_id bigint NOT NULL,
    current_level integer NOT NULL,
    target_level integer,
    assessed_at date NOT NULL,
    assessed_by bigint,
    notes text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: user_competencies_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_competencies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_competencies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_competencies_id_seq OWNED BY public.user_competencies.id;


--
-- Name: user_document_status; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_document_status (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    user_id bigint NOT NULL,
    required_document_id bigint NOT NULL,
    document_id bigint,
    status character varying(64) DEFAULT 'missing'::character varying NOT NULL,
    expiry_date date,
    last_reminded_at date,
    rejection_reason text,
    reviewed_by bigint,
    reviewed_at timestamp with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT user_document_status_status_check CHECK (((status)::text = ANY (ARRAY[('missing'::character varying)::text, ('pending'::character varying)::text, ('approved'::character varying)::text, ('rejected'::character varying)::text, ('expired'::character varying)::text])))
);


--
-- Name: user_document_status_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_document_status_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_document_status_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_document_status_id_seq OWNED BY public.user_document_status.id;


--
-- Name: user_learning_paths; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_learning_paths (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    user_id bigint NOT NULL,
    learning_path_id bigint NOT NULL,
    status character varying(64) DEFAULT 'not_started'::character varying NOT NULL,
    progress numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    started_at timestamp with time zone,
    completed_at timestamp with time zone,
    due_date date,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT user_learning_paths_status_check CHECK (((status)::text = ANY (ARRAY[('not_started'::character varying)::text, ('in_progress'::character varying)::text, ('completed'::character varying)::text])))
);


--
-- Name: user_learning_paths_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_learning_paths_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_learning_paths_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_learning_paths_id_seq OWNED BY public.user_learning_paths.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    company_id bigint,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    phone character varying(255),
    avatar character varying(255),
    title character varying(255),
    department character varying(255),
    type character varying(64) DEFAULT 'user'::character varying NOT NULL,
    email_verified_at timestamp with time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    two_factor_enabled boolean DEFAULT false NOT NULL,
    two_factor_secret text,
    two_factor_recovery_codes text,
    is_active boolean DEFAULT true NOT NULL,
    last_login_at timestamp with time zone,
    last_login_ip character varying(255),
    preferences jsonb,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone,
    invitation_token character varying(255),
    invited_at timestamp with time zone,
    invitation_accepted_at timestamp with time zone,
    must_change_password boolean DEFAULT false NOT NULL,
    last_company_id bigint,
    CONSTRAINT users_type_check CHECK (((type)::text = ANY (ARRAY[('super_admin'::character varying)::text, ('company_admin'::character varying)::text, ('user'::character varying)::text])))
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: webhook_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.webhook_logs (
    id bigint NOT NULL,
    webhook_id bigint NOT NULL,
    event character varying(255) NOT NULL,
    payload text NOT NULL,
    status_code integer,
    response text,
    error_message text,
    is_successful boolean DEFAULT false NOT NULL,
    triggered_at timestamp with time zone NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: webhook_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.webhook_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: webhook_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.webhook_logs_id_seq OWNED BY public.webhook_logs.id;


--
-- Name: webhooks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.webhooks (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    url text NOT NULL,
    secret character varying(255),
    events jsonb,
    is_active boolean DEFAULT true NOT NULL,
    timeout integer DEFAULT 30 NOT NULL,
    retry_count integer DEFAULT 3 NOT NULL,
    last_triggered_at timestamp with time zone,
    success_count integer DEFAULT 0 NOT NULL,
    failure_count integer DEFAULT 0 NOT NULL,
    created_by bigint,
    updated_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    deleted_at timestamp with time zone
);


--
-- Name: webhooks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.webhooks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: webhooks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.webhooks_id_seq OWNED BY public.webhooks.id;


--
-- Name: work_schedules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.work_schedules (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    type character varying(255) DEFAULT 'fixed'::character varying NOT NULL,
    working_days jsonb,
    default_start_time time(0) without time zone,
    default_end_time time(0) without time zone,
    break_start time(0) without time zone,
    break_end time(0) without time zone,
    break_duration_minutes integer DEFAULT 60 NOT NULL,
    daily_hours numeric(4,2) DEFAULT '8'::numeric NOT NULL,
    weekly_hours numeric(5,2) DEFAULT '40'::numeric NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: work_schedules_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.work_schedules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: work_schedules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.work_schedules_id_seq OWNED BY public.work_schedules.id;


--
-- Name: accrual_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accrual_logs ALTER COLUMN id SET DEFAULT nextval('public.accrual_logs_id_seq'::regclass);


--
-- Name: accrual_policies id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accrual_policies ALTER COLUMN id SET DEFAULT nextval('public.accrual_policies_id_seq'::regclass);


--
-- Name: activity_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_logs ALTER COLUMN id SET DEFAULT nextval('public.activity_logs_id_seq'::regclass);


--
-- Name: announcement_reads id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.announcement_reads ALTER COLUMN id SET DEFAULT nextval('public.announcement_reads_id_seq'::regclass);


--
-- Name: announcements id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.announcements ALTER COLUMN id SET DEFAULT nextval('public.announcements_id_seq'::regclass);


--
-- Name: api_keys id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.api_keys ALTER COLUMN id SET DEFAULT nextval('public.api_keys_id_seq'::regclass);


--
-- Name: application_forms id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.application_forms ALTER COLUMN id SET DEFAULT nextval('public.application_forms_id_seq'::regclass);


--
-- Name: application_sources id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.application_sources ALTER COLUMN id SET DEFAULT nextval('public.application_sources_id_seq'::regclass);


--
-- Name: application_status_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.application_status_logs ALTER COLUMN id SET DEFAULT nextval('public.application_status_logs_id_seq'::regclass);


--
-- Name: approval_delegations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_delegations ALTER COLUMN id SET DEFAULT nextval('public.approval_delegations_id_seq'::regclass);


--
-- Name: approval_escalation_alerts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_escalation_alerts ALTER COLUMN id SET DEFAULT nextval('public.approval_escalation_alerts_id_seq'::regclass);


--
-- Name: approval_instances id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_instances ALTER COLUMN id SET DEFAULT nextval('public.approval_instances_id_seq'::regclass);


--
-- Name: approval_records id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_records ALTER COLUMN id SET DEFAULT nextval('public.approval_records_id_seq'::regclass);


--
-- Name: approval_steps id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_steps ALTER COLUMN id SET DEFAULT nextval('public.approval_steps_id_seq'::regclass);


--
-- Name: approval_workflows id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_workflows ALTER COLUMN id SET DEFAULT nextval('public.approval_workflows_id_seq'::regclass);


--
-- Name: asset_assignments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_assignments ALTER COLUMN id SET DEFAULT nextval('public.asset_assignments_id_seq'::regclass);


--
-- Name: asset_categories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_categories ALTER COLUMN id SET DEFAULT nextval('public.asset_categories_id_seq'::regclass);


--
-- Name: asset_maintenance id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_maintenance ALTER COLUMN id SET DEFAULT nextval('public.asset_maintenance_id_seq'::regclass);


--
-- Name: asset_requests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_requests ALTER COLUMN id SET DEFAULT nextval('public.asset_requests_id_seq'::regclass);


--
-- Name: assets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assets ALTER COLUMN id SET DEFAULT nextval('public.assets_id_seq'::regclass);


--
-- Name: attendance_kiosk_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_kiosk_tokens ALTER COLUMN id SET DEFAULT nextval('public.attendance_kiosk_tokens_id_seq'::regclass);


--
-- Name: attendance_records id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_records ALTER COLUMN id SET DEFAULT nextval('public.attendance_records_id_seq'::regclass);


--
-- Name: branches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branches ALTER COLUMN id SET DEFAULT nextval('public.branches_id_seq'::regclass);


--
-- Name: buddy_assignments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buddy_assignments ALTER COLUMN id SET DEFAULT nextval('public.buddy_assignments_id_seq'::regclass);


--
-- Name: buddy_pool id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buddy_pool ALTER COLUMN id SET DEFAULT nextval('public.buddy_pool_id_seq'::regclass);


--
-- Name: candidate_scores id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.candidate_scores ALTER COLUMN id SET DEFAULT nextval('public.candidate_scores_id_seq'::regclass);


--
-- Name: companies id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies ALTER COLUMN id SET DEFAULT nextval('public.companies_id_seq'::regclass);


--
-- Name: company_ledger id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_ledger ALTER COLUMN id SET DEFAULT nextval('public.company_ledger_id_seq'::regclass);


--
-- Name: company_modules id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_modules ALTER COLUMN id SET DEFAULT nextval('public.company_modules_id_seq'::regclass);


--
-- Name: company_user id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_user ALTER COLUMN id SET DEFAULT nextval('public.company_user_id_seq'::regclass);


--
-- Name: competencies id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.competencies ALTER COLUMN id SET DEFAULT nextval('public.competencies_id_seq'::regclass);


--
-- Name: consent_records id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consent_records ALTER COLUMN id SET DEFAULT nextval('public.consent_records_id_seq'::regclass);


--
-- Name: continuous_feedbacks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.continuous_feedbacks ALTER COLUMN id SET DEFAULT nextval('public.continuous_feedbacks_id_seq'::regclass);


--
-- Name: custom_field_definitions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.custom_field_definitions ALTER COLUMN id SET DEFAULT nextval('public.custom_field_definitions_id_seq'::regclass);


--
-- Name: dashboard_shares id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dashboard_shares ALTER COLUMN id SET DEFAULT nextval('public.dashboard_shares_id_seq'::regclass);


--
-- Name: dashboards id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dashboards ALTER COLUMN id SET DEFAULT nextval('public.dashboards_id_seq'::regclass);


--
-- Name: data_breaches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_breaches ALTER COLUMN id SET DEFAULT nextval('public.data_breaches_id_seq'::regclass);


--
-- Name: data_processing_activities id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_processing_activities ALTER COLUMN id SET DEFAULT nextval('public.data_processing_activities_id_seq'::regclass);


--
-- Name: data_subject_export_access_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_subject_export_access_logs ALTER COLUMN id SET DEFAULT nextval('public.data_subject_export_access_logs_id_seq'::regclass);


--
-- Name: data_subject_export_packages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_subject_export_packages ALTER COLUMN id SET DEFAULT nextval('public.data_subject_export_packages_id_seq'::regclass);


--
-- Name: data_subject_requests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_subject_requests ALTER COLUMN id SET DEFAULT nextval('public.data_subject_requests_id_seq'::regclass);


--
-- Name: departments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments ALTER COLUMN id SET DEFAULT nextval('public.departments_id_seq'::regclass);


--
-- Name: destruction_approvals id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.destruction_approvals ALTER COLUMN id SET DEFAULT nextval('public.destruction_approvals_id_seq'::regclass);


--
-- Name: destruction_candidates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.destruction_candidates ALTER COLUMN id SET DEFAULT nextval('public.destruction_candidates_id_seq'::regclass);


--
-- Name: destruction_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.destruction_logs ALTER COLUMN id SET DEFAULT nextval('public.destruction_logs_id_seq'::regclass);


--
-- Name: document_approvals id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_approvals ALTER COLUMN id SET DEFAULT nextval('public.document_approvals_id_seq'::regclass);


--
-- Name: document_categories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_categories ALTER COLUMN id SET DEFAULT nextval('public.document_categories_id_seq'::regclass);


--
-- Name: document_expiry_alerts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_expiry_alerts ALTER COLUMN id SET DEFAULT nextval('public.document_expiry_alerts_id_seq'::regclass);


--
-- Name: document_versions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_versions ALTER COLUMN id SET DEFAULT nextval('public.document_versions_id_seq'::regclass);


--
-- Name: documents id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents ALTER COLUMN id SET DEFAULT nextval('public.documents_id_seq'::regclass);


--
-- Name: employee_dashboards id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_dashboards ALTER COLUMN id SET DEFAULT nextval('public.employee_dashboards_id_seq'::regclass);


--
-- Name: employee_documents id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_documents ALTER COLUMN id SET DEFAULT nextval('public.employee_documents_id_seq'::regclass);


--
-- Name: employee_request_history id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_request_history ALTER COLUMN id SET DEFAULT nextval('public.employee_request_history_id_seq'::regclass);


--
-- Name: employee_requests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_requests ALTER COLUMN id SET DEFAULT nextval('public.employee_requests_id_seq'::regclass);


--
-- Name: employee_shifts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_shifts ALTER COLUMN id SET DEFAULT nextval('public.employee_shifts_id_seq'::regclass);


--
-- Name: employees id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees ALTER COLUMN id SET DEFAULT nextval('public.employees_id_seq'::regclass);


--
-- Name: enps_records id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.enps_records ALTER COLUMN id SET DEFAULT nextval('public.enps_records_id_seq'::regclass);


--
-- Name: expense_categories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_categories ALTER COLUMN id SET DEFAULT nextval('public.expense_categories_id_seq'::regclass);


--
-- Name: expense_claims id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_claims ALTER COLUMN id SET DEFAULT nextval('public.expense_claims_id_seq'::regclass);


--
-- Name: expense_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_items ALTER COLUMN id SET DEFAULT nextval('public.expense_items_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: feedback_providers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feedback_providers ALTER COLUMN id SET DEFAULT nextval('public.feedback_providers_id_seq'::regclass);


--
-- Name: feedback_responses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feedback_responses ALTER COLUMN id SET DEFAULT nextval('public.feedback_responses_id_seq'::regclass);


--
-- Name: form_definitions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.form_definitions ALTER COLUMN id SET DEFAULT nextval('public.form_definitions_id_seq'::regclass);


--
-- Name: holidays id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.holidays ALTER COLUMN id SET DEFAULT nextval('public.holidays_id_seq'::regclass);


--
-- Name: interview_scorecards id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.interview_scorecards ALTER COLUMN id SET DEFAULT nextval('public.interview_scorecards_id_seq'::regclass);


--
-- Name: interviews id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.interviews ALTER COLUMN id SET DEFAULT nextval('public.interviews_id_seq'::regclass);


--
-- Name: job_applications id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_applications ALTER COLUMN id SET DEFAULT nextval('public.job_applications_id_seq'::regclass);


--
-- Name: job_offers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_offers ALTER COLUMN id SET DEFAULT nextval('public.job_offers_id_seq'::regclass);


--
-- Name: job_positions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_positions ALTER COLUMN id SET DEFAULT nextval('public.job_positions_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: key_result_updates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.key_result_updates ALTER COLUMN id SET DEFAULT nextval('public.key_result_updates_id_seq'::regclass);


--
-- Name: key_results id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.key_results ALTER COLUMN id SET DEFAULT nextval('public.key_results_id_seq'::regclass);


--
-- Name: learning_path_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.learning_path_items ALTER COLUMN id SET DEFAULT nextval('public.learning_path_items_id_seq'::regclass);


--
-- Name: learning_paths id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.learning_paths ALTER COLUMN id SET DEFAULT nextval('public.learning_paths_id_seq'::regclass);


--
-- Name: leave_balances id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_balances ALTER COLUMN id SET DEFAULT nextval('public.leave_balances_id_seq'::regclass);


--
-- Name: leave_requests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_requests ALTER COLUMN id SET DEFAULT nextval('public.leave_requests_id_seq'::regclass);


--
-- Name: leave_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_types ALTER COLUMN id SET DEFAULT nextval('public.leave_types_id_seq'::regclass);


--
-- Name: legal_holds id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.legal_holds ALTER COLUMN id SET DEFAULT nextval('public.legal_holds_id_seq'::regclass);


--
-- Name: license_package_modules id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.license_package_modules ALTER COLUMN id SET DEFAULT nextval('public.license_package_modules_id_seq'::regclass);


--
-- Name: license_packages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.license_packages ALTER COLUMN id SET DEFAULT nextval('public.license_packages_id_seq'::regclass);


--
-- Name: lookups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lookups ALTER COLUMN id SET DEFAULT nextval('public.lookups_id_seq'::regclass);


--
-- Name: mandatory_trainings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mandatory_trainings ALTER COLUMN id SET DEFAULT nextval('public.mandatory_trainings_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: milestone_completions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.milestone_completions ALTER COLUMN id SET DEFAULT nextval('public.milestone_completions_id_seq'::regclass);


--
-- Name: modules id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modules ALTER COLUMN id SET DEFAULT nextval('public.modules_id_seq'::regclass);


--
-- Name: notification_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_templates ALTER COLUMN id SET DEFAULT nextval('public.notification_templates_id_seq'::regclass);


--
-- Name: objectives id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.objectives ALTER COLUMN id SET DEFAULT nextval('public.objectives_id_seq'::regclass);


--
-- Name: onboarding_milestones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_milestones ALTER COLUMN id SET DEFAULT nextval('public.onboarding_milestones_id_seq'::regclass);


--
-- Name: onboarding_processes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_processes ALTER COLUMN id SET DEFAULT nextval('public.onboarding_processes_id_seq'::regclass);


--
-- Name: onboarding_surveys id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_surveys ALTER COLUMN id SET DEFAULT nextval('public.onboarding_surveys_id_seq'::regclass);


--
-- Name: onboarding_tasks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_tasks ALTER COLUMN id SET DEFAULT nextval('public.onboarding_tasks_id_seq'::regclass);


--
-- Name: onboarding_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_templates ALTER COLUMN id SET DEFAULT nextval('public.onboarding_templates_id_seq'::regclass);


--
-- Name: one_on_one_meetings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.one_on_one_meetings ALTER COLUMN id SET DEFAULT nextval('public.one_on_one_meetings_id_seq'::regclass);


--
-- Name: organizations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizations ALTER COLUMN id SET DEFAULT nextval('public.organizations_id_seq'::regclass);


--
-- Name: payslips id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payslips ALTER COLUMN id SET DEFAULT nextval('public.payslips_id_seq'::regclass);


--
-- Name: performance_criteria id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_criteria ALTER COLUMN id SET DEFAULT nextval('public.performance_criteria_id_seq'::regclass);


--
-- Name: performance_periods id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_periods ALTER COLUMN id SET DEFAULT nextval('public.performance_periods_id_seq'::regclass);


--
-- Name: performance_reviews id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_reviews ALTER COLUMN id SET DEFAULT nextval('public.performance_reviews_id_seq'::regclass);


--
-- Name: performance_scores id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_scores ALTER COLUMN id SET DEFAULT nextval('public.performance_scores_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: position_competencies id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.position_competencies ALTER COLUMN id SET DEFAULT nextval('public.position_competencies_id_seq'::regclass);


--
-- Name: positions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.positions ALTER COLUMN id SET DEFAULT nextval('public.positions_id_seq'::regclass);


--
-- Name: preboarding_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.preboarding_tokens ALTER COLUMN id SET DEFAULT nextval('public.preboarding_tokens_id_seq'::regclass);


--
-- Name: privacy_notices id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.privacy_notices ALTER COLUMN id SET DEFAULT nextval('public.privacy_notices_id_seq'::regclass);


--
-- Name: report_access_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_access_logs ALTER COLUMN id SET DEFAULT nextval('public.report_access_logs_id_seq'::regclass);


--
-- Name: report_folders id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_folders ALTER COLUMN id SET DEFAULT nextval('public.report_folders_id_seq'::regclass);


--
-- Name: report_measures id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_measures ALTER COLUMN id SET DEFAULT nextval('public.report_measures_id_seq'::regclass);


--
-- Name: report_schedules id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_schedules ALTER COLUMN id SET DEFAULT nextval('public.report_schedules_id_seq'::regclass);


--
-- Name: report_shares id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_shares ALTER COLUMN id SET DEFAULT nextval('public.report_shares_id_seq'::regclass);


--
-- Name: request_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.request_types ALTER COLUMN id SET DEFAULT nextval('public.request_types_id_seq'::regclass);


--
-- Name: required_documents id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.required_documents ALTER COLUMN id SET DEFAULT nextval('public.required_documents_id_seq'::regclass);


--
-- Name: retention_decisions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.retention_decisions ALTER COLUMN id SET DEFAULT nextval('public.retention_decisions_id_seq'::regclass);


--
-- Name: retention_policies id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.retention_policies ALTER COLUMN id SET DEFAULT nextval('public.retention_policies_id_seq'::regclass);


--
-- Name: role_default_dashboards id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_default_dashboards ALTER COLUMN id SET DEFAULT nextval('public.role_default_dashboards_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: salary_bands id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_bands ALTER COLUMN id SET DEFAULT nextval('public.salary_bands_id_seq'::regclass);


--
-- Name: salary_records id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_records ALTER COLUMN id SET DEFAULT nextval('public.salary_records_id_seq'::regclass);


--
-- Name: salary_review_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_review_items ALTER COLUMN id SET DEFAULT nextval('public.salary_review_items_id_seq'::regclass);


--
-- Name: salary_review_periods id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_review_periods ALTER COLUMN id SET DEFAULT nextval('public.salary_review_periods_id_seq'::regclass);


--
-- Name: saved_reports id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.saved_reports ALTER COLUMN id SET DEFAULT nextval('public.saved_reports_id_seq'::regclass);


--
-- Name: setting_values id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.setting_values ALTER COLUMN id SET DEFAULT nextval('public.setting_values_id_seq'::regclass);


--
-- Name: shifts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shifts ALTER COLUMN id SET DEFAULT nextval('public.shifts_id_seq'::regclass);


--
-- Name: software_license_assignments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.software_license_assignments ALTER COLUMN id SET DEFAULT nextval('public.software_license_assignments_id_seq'::regclass);


--
-- Name: software_licenses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.software_licenses ALTER COLUMN id SET DEFAULT nextval('public.software_licenses_id_seq'::regclass);


--
-- Name: survey_questions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_questions ALTER COLUMN id SET DEFAULT nextval('public.survey_questions_id_seq'::regclass);


--
-- Name: survey_responses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_responses ALTER COLUMN id SET DEFAULT nextval('public.survey_responses_id_seq'::regclass);


--
-- Name: survey_submissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_submissions ALTER COLUMN id SET DEFAULT nextval('public.survey_submissions_id_seq'::regclass);


--
-- Name: surveys id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.surveys ALTER COLUMN id SET DEFAULT nextval('public.surveys_id_seq'::regclass);


--
-- Name: telescope_entries sequence; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telescope_entries ALTER COLUMN sequence SET DEFAULT nextval('public.telescope_entries_sequence_seq'::regclass);


--
-- Name: timesheets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.timesheets ALTER COLUMN id SET DEFAULT nextval('public.timesheets_id_seq'::regclass);


--
-- Name: training_certificates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.training_certificates ALTER COLUMN id SET DEFAULT nextval('public.training_certificates_id_seq'::regclass);


--
-- Name: training_participants id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.training_participants ALTER COLUMN id SET DEFAULT nextval('public.training_participants_id_seq'::regclass);


--
-- Name: training_requests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.training_requests ALTER COLUMN id SET DEFAULT nextval('public.training_requests_id_seq'::regclass);


--
-- Name: training_sessions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.training_sessions ALTER COLUMN id SET DEFAULT nextval('public.training_sessions_id_seq'::regclass);


--
-- Name: trainings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trainings ALTER COLUMN id SET DEFAULT nextval('public.trainings_id_seq'::regclass);


--
-- Name: user_competencies id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_competencies ALTER COLUMN id SET DEFAULT nextval('public.user_competencies_id_seq'::regclass);


--
-- Name: user_document_status id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_document_status ALTER COLUMN id SET DEFAULT nextval('public.user_document_status_id_seq'::regclass);


--
-- Name: user_learning_paths id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_learning_paths ALTER COLUMN id SET DEFAULT nextval('public.user_learning_paths_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: webhook_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_logs ALTER COLUMN id SET DEFAULT nextval('public.webhook_logs_id_seq'::regclass);


--
-- Name: webhooks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhooks ALTER COLUMN id SET DEFAULT nextval('public.webhooks_id_seq'::regclass);


--
-- Name: work_schedules id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_schedules ALTER COLUMN id SET DEFAULT nextval('public.work_schedules_id_seq'::regclass);


--
-- Name: accrual_logs accrual_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accrual_logs
    ADD CONSTRAINT accrual_logs_pkey PRIMARY KEY (id);


--
-- Name: accrual_policies accrual_policies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accrual_policies
    ADD CONSTRAINT accrual_policies_pkey PRIMARY KEY (id);


--
-- Name: activity_logs activity_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_logs
    ADD CONSTRAINT activity_logs_pkey PRIMARY KEY (id);


--
-- Name: announcement_reads announcement_reads_announcement_id_employee_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.announcement_reads
    ADD CONSTRAINT announcement_reads_announcement_id_employee_id_unique UNIQUE (announcement_id, employee_id);


--
-- Name: announcement_reads announcement_reads_announcement_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.announcement_reads
    ADD CONSTRAINT announcement_reads_announcement_id_user_id_unique UNIQUE (announcement_id, user_id);


--
-- Name: announcement_reads announcement_reads_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.announcement_reads
    ADD CONSTRAINT announcement_reads_pkey PRIMARY KEY (id);


--
-- Name: announcements announcements_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.announcements
    ADD CONSTRAINT announcements_pkey PRIMARY KEY (id);


--
-- Name: api_keys api_keys_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.api_keys
    ADD CONSTRAINT api_keys_key_unique UNIQUE (key);


--
-- Name: api_keys api_keys_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.api_keys
    ADD CONSTRAINT api_keys_pkey PRIMARY KEY (id);


--
-- Name: application_forms application_forms_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.application_forms
    ADD CONSTRAINT application_forms_pkey PRIMARY KEY (id);


--
-- Name: application_sources application_sources_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.application_sources
    ADD CONSTRAINT application_sources_code_unique UNIQUE (code);


--
-- Name: application_sources application_sources_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.application_sources
    ADD CONSTRAINT application_sources_pkey PRIMARY KEY (id);


--
-- Name: application_status_logs application_status_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.application_status_logs
    ADD CONSTRAINT application_status_logs_pkey PRIMARY KEY (id);


--
-- Name: approval_delegations approval_delegations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_delegations
    ADD CONSTRAINT approval_delegations_pkey PRIMARY KEY (id);


--
-- Name: approval_escalation_alerts approval_escalation_alerts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_escalation_alerts
    ADD CONSTRAINT approval_escalation_alerts_pkey PRIMARY KEY (id);


--
-- Name: approval_escalation_alerts approval_escalation_alerts_record_level_uq; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_escalation_alerts
    ADD CONSTRAINT approval_escalation_alerts_record_level_uq UNIQUE (approval_record_id, alert_level);


--
-- Name: approval_instances approval_instances_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_instances
    ADD CONSTRAINT approval_instances_pkey PRIMARY KEY (id);


--
-- Name: approval_records approval_records_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_records
    ADD CONSTRAINT approval_records_pkey PRIMARY KEY (id);


--
-- Name: approval_steps approval_steps_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_steps
    ADD CONSTRAINT approval_steps_pkey PRIMARY KEY (id);


--
-- Name: approval_workflows approval_workflows_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_workflows
    ADD CONSTRAINT approval_workflows_pkey PRIMARY KEY (id);


--
-- Name: asset_assignments asset_assignments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_assignments
    ADD CONSTRAINT asset_assignments_pkey PRIMARY KEY (id);


--
-- Name: asset_categories asset_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_categories
    ADD CONSTRAINT asset_categories_pkey PRIMARY KEY (id);


--
-- Name: asset_maintenance asset_maintenance_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_maintenance
    ADD CONSTRAINT asset_maintenance_pkey PRIMARY KEY (id);


--
-- Name: asset_requests asset_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_requests
    ADD CONSTRAINT asset_requests_pkey PRIMARY KEY (id);


--
-- Name: assets assets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assets
    ADD CONSTRAINT assets_pkey PRIMARY KEY (id);


--
-- Name: attendance_kiosk_tokens attendance_kiosk_tokens_jti_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_kiosk_tokens
    ADD CONSTRAINT attendance_kiosk_tokens_jti_unique UNIQUE (jti);


--
-- Name: attendance_kiosk_tokens attendance_kiosk_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_kiosk_tokens
    ADD CONSTRAINT attendance_kiosk_tokens_pkey PRIMARY KEY (id);


--
-- Name: attendance_kiosk_tokens attendance_kiosk_tokens_token_hash_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_kiosk_tokens
    ADD CONSTRAINT attendance_kiosk_tokens_token_hash_unique UNIQUE (token_hash);


--
-- Name: attendance_records attendance_records_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_records
    ADD CONSTRAINT attendance_records_pkey PRIMARY KEY (id);


--
-- Name: attendance_records attendance_records_user_id_date_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_records
    ADD CONSTRAINT attendance_records_user_id_date_unique UNIQUE (user_id, date);


--
-- Name: branches branches_company_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_company_code_unique UNIQUE (company_id, code);


--
-- Name: branches branches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_pkey PRIMARY KEY (id);


--
-- Name: buddy_assignments buddy_assignments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buddy_assignments
    ADD CONSTRAINT buddy_assignments_pkey PRIMARY KEY (id);


--
-- Name: buddy_pool buddy_pool_company_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buddy_pool
    ADD CONSTRAINT buddy_pool_company_id_user_id_unique UNIQUE (company_id, user_id);


--
-- Name: buddy_pool buddy_pool_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buddy_pool
    ADD CONSTRAINT buddy_pool_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: candidate_scores candidate_scores_job_application_id_job_position_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.candidate_scores
    ADD CONSTRAINT candidate_scores_job_application_id_job_position_id_unique UNIQUE (job_application_id, job_position_id);


--
-- Name: candidate_scores candidate_scores_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.candidate_scores
    ADD CONSTRAINT candidate_scores_pkey PRIMARY KEY (id);


--
-- Name: companies companies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_pkey PRIMARY KEY (id);


--
-- Name: companies companies_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_slug_unique UNIQUE (slug);


--
-- Name: company_ledger company_ledger_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_ledger
    ADD CONSTRAINT company_ledger_pkey PRIMARY KEY (id);


--
-- Name: company_modules company_modules_company_id_module_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_modules
    ADD CONSTRAINT company_modules_company_id_module_id_unique UNIQUE (company_id, module_id);


--
-- Name: company_modules company_modules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_modules
    ADD CONSTRAINT company_modules_pkey PRIMARY KEY (id);


--
-- Name: company_user company_user_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_user
    ADD CONSTRAINT company_user_pkey PRIMARY KEY (id);


--
-- Name: company_user company_user_user_id_company_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_user
    ADD CONSTRAINT company_user_user_id_company_id_unique UNIQUE (user_id, company_id);


--
-- Name: competencies competencies_company_id_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.competencies
    ADD CONSTRAINT competencies_company_id_name_unique UNIQUE (company_id, name);


--
-- Name: competencies competencies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.competencies
    ADD CONSTRAINT competencies_pkey PRIMARY KEY (id);


--
-- Name: consent_records consent_records_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consent_records
    ADD CONSTRAINT consent_records_pkey PRIMARY KEY (id);


--
-- Name: continuous_feedbacks continuous_feedbacks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.continuous_feedbacks
    ADD CONSTRAINT continuous_feedbacks_pkey PRIMARY KEY (id);


--
-- Name: custom_field_definitions custom_field_definitions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.custom_field_definitions
    ADD CONSTRAINT custom_field_definitions_pkey PRIMARY KEY (id);


--
-- Name: dashboard_shares dashboard_shares_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dashboard_shares
    ADD CONSTRAINT dashboard_shares_pkey PRIMARY KEY (id);


--
-- Name: dashboards dashboards_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dashboards
    ADD CONSTRAINT dashboards_pkey PRIMARY KEY (id);


--
-- Name: data_breaches data_breaches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_breaches
    ADD CONSTRAINT data_breaches_pkey PRIMARY KEY (id);


--
-- Name: data_processing_activities data_processing_activities_company_id_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_processing_activities
    ADD CONSTRAINT data_processing_activities_company_id_key_unique UNIQUE (company_id, key);


--
-- Name: data_processing_activities data_processing_activities_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_processing_activities
    ADD CONSTRAINT data_processing_activities_pkey PRIMARY KEY (id);


--
-- Name: data_subject_export_access_logs data_subject_export_access_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_subject_export_access_logs
    ADD CONSTRAINT data_subject_export_access_logs_pkey PRIMARY KEY (id);


--
-- Name: data_subject_export_packages data_subject_export_packages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_subject_export_packages
    ADD CONSTRAINT data_subject_export_packages_pkey PRIMARY KEY (id);


--
-- Name: data_subject_export_packages data_subject_export_packages_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_subject_export_packages
    ADD CONSTRAINT data_subject_export_packages_uuid_unique UNIQUE (uuid);


--
-- Name: data_subject_requests data_subject_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_subject_requests
    ADD CONSTRAINT data_subject_requests_pkey PRIMARY KEY (id);


--
-- Name: departments departments_company_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_company_id_code_unique UNIQUE (company_id, code);


--
-- Name: departments departments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_pkey PRIMARY KEY (id);


--
-- Name: destruction_approvals destruction_approvals_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.destruction_approvals
    ADD CONSTRAINT destruction_approvals_pkey PRIMARY KEY (id);


--
-- Name: destruction_candidates destruction_candidates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.destruction_candidates
    ADD CONSTRAINT destruction_candidates_pkey PRIMARY KEY (id);


--
-- Name: destruction_logs destruction_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.destruction_logs
    ADD CONSTRAINT destruction_logs_pkey PRIMARY KEY (id);


--
-- Name: document_approvals document_approvals_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_approvals
    ADD CONSTRAINT document_approvals_pkey PRIMARY KEY (id);


--
-- Name: document_categories document_categories_company_id_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_categories
    ADD CONSTRAINT document_categories_company_id_slug_unique UNIQUE (company_id, slug);


--
-- Name: document_categories document_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_categories
    ADD CONSTRAINT document_categories_pkey PRIMARY KEY (id);


--
-- Name: document_expiry_alerts document_expiry_alerts_doc_threshold_uq; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_expiry_alerts
    ADD CONSTRAINT document_expiry_alerts_doc_threshold_uq UNIQUE (employee_document_id, threshold_days);


--
-- Name: document_expiry_alerts document_expiry_alerts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_expiry_alerts
    ADD CONSTRAINT document_expiry_alerts_pkey PRIMARY KEY (id);


--
-- Name: document_versions document_versions_document_id_version_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_versions
    ADD CONSTRAINT document_versions_document_id_version_number_unique UNIQUE (document_id, version_number);


--
-- Name: document_versions document_versions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_versions
    ADD CONSTRAINT document_versions_pkey PRIMARY KEY (id);


--
-- Name: documents documents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_pkey PRIMARY KEY (id);


--
-- Name: employee_dashboards employee_dashboards_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_dashboards
    ADD CONSTRAINT employee_dashboards_pkey PRIMARY KEY (id);


--
-- Name: employee_documents employee_documents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_documents
    ADD CONSTRAINT employee_documents_pkey PRIMARY KEY (id);


--
-- Name: employee_request_history employee_request_history_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_request_history
    ADD CONSTRAINT employee_request_history_pkey PRIMARY KEY (id);


--
-- Name: employee_requests employee_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_requests
    ADD CONSTRAINT employee_requests_pkey PRIMARY KEY (id);


--
-- Name: employee_shifts employee_shifts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_shifts
    ADD CONSTRAINT employee_shifts_pkey PRIMARY KEY (id);


--
-- Name: employee_shifts employee_shifts_user_id_date_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_shifts
    ADD CONSTRAINT employee_shifts_user_id_date_unique UNIQUE (user_id, date);


--
-- Name: employees employees_company_id_employee_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_company_id_employee_code_unique UNIQUE (company_id, employee_code);


--
-- Name: employees employees_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_pkey PRIMARY KEY (id);


--
-- Name: enps_records enps_records_company_id_period_date_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.enps_records
    ADD CONSTRAINT enps_records_company_id_period_date_unique UNIQUE (company_id, period_date);


--
-- Name: enps_records enps_records_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.enps_records
    ADD CONSTRAINT enps_records_pkey PRIMARY KEY (id);


--
-- Name: expense_categories expense_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_categories
    ADD CONSTRAINT expense_categories_pkey PRIMARY KEY (id);


--
-- Name: expense_claims expense_claims_claim_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_claims
    ADD CONSTRAINT expense_claims_claim_number_unique UNIQUE (claim_number);


--
-- Name: expense_claims expense_claims_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_claims
    ADD CONSTRAINT expense_claims_pkey PRIMARY KEY (id);


--
-- Name: expense_items expense_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_items
    ADD CONSTRAINT expense_items_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: feedback_providers feedback_providers_performance_review_id_provider_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feedback_providers
    ADD CONSTRAINT feedback_providers_performance_review_id_provider_id_unique UNIQUE (performance_review_id, provider_id);


--
-- Name: feedback_providers feedback_providers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feedback_providers
    ADD CONSTRAINT feedback_providers_pkey PRIMARY KEY (id);


--
-- Name: feedback_responses feedback_response_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feedback_responses
    ADD CONSTRAINT feedback_response_unique UNIQUE (feedback_provider_id, performance_criteria_id);


--
-- Name: feedback_responses feedback_responses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feedback_responses
    ADD CONSTRAINT feedback_responses_pkey PRIMARY KEY (id);


--
-- Name: form_definitions form_definitions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.form_definitions
    ADD CONSTRAINT form_definitions_pkey PRIMARY KEY (id);


--
-- Name: holidays holidays_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.holidays
    ADD CONSTRAINT holidays_pkey PRIMARY KEY (id);


--
-- Name: interview_scorecards interview_scorecards_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.interview_scorecards
    ADD CONSTRAINT interview_scorecards_pkey PRIMARY KEY (id);


--
-- Name: interviews interviews_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.interviews
    ADD CONSTRAINT interviews_pkey PRIMARY KEY (id);


--
-- Name: job_applications job_applications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_applications
    ADD CONSTRAINT job_applications_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: job_offers job_offers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_offers
    ADD CONSTRAINT job_offers_pkey PRIMARY KEY (id);


--
-- Name: job_positions job_positions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_positions
    ADD CONSTRAINT job_positions_pkey PRIMARY KEY (id);


--
-- Name: job_positions job_positions_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_positions
    ADD CONSTRAINT job_positions_slug_unique UNIQUE (slug);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: key_result_updates key_result_updates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.key_result_updates
    ADD CONSTRAINT key_result_updates_pkey PRIMARY KEY (id);


--
-- Name: key_results key_results_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.key_results
    ADD CONSTRAINT key_results_pkey PRIMARY KEY (id);


--
-- Name: learning_path_items learning_path_items_learning_path_id_training_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.learning_path_items
    ADD CONSTRAINT learning_path_items_learning_path_id_training_id_unique UNIQUE (learning_path_id, training_id);


--
-- Name: learning_path_items learning_path_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.learning_path_items
    ADD CONSTRAINT learning_path_items_pkey PRIMARY KEY (id);


--
-- Name: learning_paths learning_paths_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.learning_paths
    ADD CONSTRAINT learning_paths_pkey PRIMARY KEY (id);


--
-- Name: leave_balances leave_balances_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_balances
    ADD CONSTRAINT leave_balances_pkey PRIMARY KEY (id);


--
-- Name: leave_balances leave_balances_user_id_leave_type_id_year_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_balances
    ADD CONSTRAINT leave_balances_user_id_leave_type_id_year_unique UNIQUE (user_id, leave_type_id, year);


--
-- Name: leave_requests leave_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_requests
    ADD CONSTRAINT leave_requests_pkey PRIMARY KEY (id);


--
-- Name: leave_types leave_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_types
    ADD CONSTRAINT leave_types_pkey PRIMARY KEY (id);


--
-- Name: legal_holds legal_holds_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.legal_holds
    ADD CONSTRAINT legal_holds_pkey PRIMARY KEY (id);


--
-- Name: license_package_modules license_package_modules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.license_package_modules
    ADD CONSTRAINT license_package_modules_pkey PRIMARY KEY (id);


--
-- Name: license_packages license_packages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.license_packages
    ADD CONSTRAINT license_packages_pkey PRIMARY KEY (id);


--
-- Name: license_packages license_packages_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.license_packages
    ADD CONSTRAINT license_packages_slug_unique UNIQUE (slug);


--
-- Name: lookups lookups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lookups
    ADD CONSTRAINT lookups_pkey PRIMARY KEY (id);


--
-- Name: mandatory_trainings mandatory_trainings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mandatory_trainings
    ADD CONSTRAINT mandatory_trainings_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: milestone_completions milestone_completions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.milestone_completions
    ADD CONSTRAINT milestone_completions_pkey PRIMARY KEY (id);


--
-- Name: milestone_completions milestone_completions_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.milestone_completions
    ADD CONSTRAINT milestone_completions_unique UNIQUE (onboarding_process_id, onboarding_milestone_id);


--
-- Name: model_has_permissions model_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_pkey PRIMARY KEY (permission_id, model_id, model_type);


--
-- Name: model_has_roles model_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_pkey PRIMARY KEY (role_id, model_id, model_type);


--
-- Name: modules modules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modules
    ADD CONSTRAINT modules_pkey PRIMARY KEY (id);


--
-- Name: modules modules_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modules
    ADD CONSTRAINT modules_slug_unique UNIQUE (slug);


--
-- Name: notification_templates notification_templates_company_id_event_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_templates
    ADD CONSTRAINT notification_templates_company_id_event_key_unique UNIQUE (company_id, event_key);


--
-- Name: notification_templates notification_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_templates
    ADD CONSTRAINT notification_templates_pkey PRIMARY KEY (id);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- Name: objectives objectives_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.objectives
    ADD CONSTRAINT objectives_pkey PRIMARY KEY (id);


--
-- Name: onboarding_milestones onboarding_milestones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_milestones
    ADD CONSTRAINT onboarding_milestones_pkey PRIMARY KEY (id);


--
-- Name: onboarding_processes onboarding_processes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_processes
    ADD CONSTRAINT onboarding_processes_pkey PRIMARY KEY (id);


--
-- Name: onboarding_surveys onboarding_surveys_onboarding_process_id_survey_type_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_surveys
    ADD CONSTRAINT onboarding_surveys_onboarding_process_id_survey_type_unique UNIQUE (onboarding_process_id, survey_type);


--
-- Name: onboarding_surveys onboarding_surveys_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_surveys
    ADD CONSTRAINT onboarding_surveys_pkey PRIMARY KEY (id);


--
-- Name: onboarding_tasks onboarding_tasks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_tasks
    ADD CONSTRAINT onboarding_tasks_pkey PRIMARY KEY (id);


--
-- Name: onboarding_templates onboarding_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_templates
    ADD CONSTRAINT onboarding_templates_pkey PRIMARY KEY (id);


--
-- Name: one_on_one_meetings one_on_one_meetings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.one_on_one_meetings
    ADD CONSTRAINT one_on_one_meetings_pkey PRIMARY KEY (id);


--
-- Name: organizations organizations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizations
    ADD CONSTRAINT organizations_pkey PRIMARY KEY (id);


--
-- Name: organizations organizations_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizations
    ADD CONSTRAINT organizations_slug_unique UNIQUE (slug);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: payslips payslips_employee_id_period_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payslips
    ADD CONSTRAINT payslips_employee_id_period_unique UNIQUE (employee_id, period);


--
-- Name: payslips payslips_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payslips
    ADD CONSTRAINT payslips_pkey PRIMARY KEY (id);


--
-- Name: performance_criteria performance_criteria_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_criteria
    ADD CONSTRAINT performance_criteria_pkey PRIMARY KEY (id);


--
-- Name: performance_periods performance_periods_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_periods
    ADD CONSTRAINT performance_periods_pkey PRIMARY KEY (id);


--
-- Name: performance_reviews performance_reviews_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_reviews
    ADD CONSTRAINT performance_reviews_pkey PRIMARY KEY (id);


--
-- Name: performance_scores performance_scores_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_scores
    ADD CONSTRAINT performance_scores_pkey PRIMARY KEY (id);


--
-- Name: performance_scores performance_scores_review_id_criteria_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_scores
    ADD CONSTRAINT performance_scores_review_id_criteria_id_unique UNIQUE (review_id, criteria_id);


--
-- Name: permissions permissions_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: license_package_modules pkg_module_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.license_package_modules
    ADD CONSTRAINT pkg_module_unique UNIQUE (license_package_id, module_id);


--
-- Name: position_competencies position_comp_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.position_competencies
    ADD CONSTRAINT position_comp_unique UNIQUE (company_id, position_name, competency_id);


--
-- Name: position_competencies position_competencies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.position_competencies
    ADD CONSTRAINT position_competencies_pkey PRIMARY KEY (id);


--
-- Name: positions positions_company_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.positions
    ADD CONSTRAINT positions_company_id_code_unique UNIQUE (company_id, code);


--
-- Name: positions positions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.positions
    ADD CONSTRAINT positions_pkey PRIMARY KEY (id);


--
-- Name: preboarding_tokens preboarding_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.preboarding_tokens
    ADD CONSTRAINT preboarding_tokens_pkey PRIMARY KEY (id);


--
-- Name: preboarding_tokens preboarding_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.preboarding_tokens
    ADD CONSTRAINT preboarding_tokens_token_unique UNIQUE (token);


--
-- Name: privacy_notices privacy_notices_company_id_audience_version_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.privacy_notices
    ADD CONSTRAINT privacy_notices_company_id_audience_version_unique UNIQUE (company_id, audience, version);


--
-- Name: privacy_notices privacy_notices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.privacy_notices
    ADD CONSTRAINT privacy_notices_pkey PRIMARY KEY (id);


--
-- Name: report_access_logs report_access_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_access_logs
    ADD CONSTRAINT report_access_logs_pkey PRIMARY KEY (id);


--
-- Name: report_folders report_folders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_folders
    ADD CONSTRAINT report_folders_pkey PRIMARY KEY (id);


--
-- Name: report_measures report_measures_company_id_dataset_key_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_measures
    ADD CONSTRAINT report_measures_company_id_dataset_key_key_unique UNIQUE (company_id, dataset_key, key);


--
-- Name: report_measures report_measures_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_measures
    ADD CONSTRAINT report_measures_pkey PRIMARY KEY (id);


--
-- Name: report_schedules report_schedules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_schedules
    ADD CONSTRAINT report_schedules_pkey PRIMARY KEY (id);


--
-- Name: report_shares report_shares_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_shares
    ADD CONSTRAINT report_shares_pkey PRIMARY KEY (id);


--
-- Name: request_types request_types_company_id_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.request_types
    ADD CONSTRAINT request_types_company_id_slug_unique UNIQUE (company_id, slug);


--
-- Name: request_types request_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.request_types
    ADD CONSTRAINT request_types_pkey PRIMARY KEY (id);


--
-- Name: required_documents required_documents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.required_documents
    ADD CONSTRAINT required_documents_pkey PRIMARY KEY (id);


--
-- Name: retention_decisions retention_decisions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.retention_decisions
    ADD CONSTRAINT retention_decisions_pkey PRIMARY KEY (id);


--
-- Name: retention_policies retention_policies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.retention_policies
    ADD CONSTRAINT retention_policies_pkey PRIMARY KEY (id);


--
-- Name: role_default_dashboards role_default_dashboards_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_default_dashboards
    ADD CONSTRAINT role_default_dashboards_pkey PRIMARY KEY (id);


--
-- Name: role_default_dashboards role_default_dashboards_role_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_default_dashboards
    ADD CONSTRAINT role_default_dashboards_role_key_unique UNIQUE (role_key);


--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: roles roles_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: salary_bands salary_bands_company_id_position_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_bands
    ADD CONSTRAINT salary_bands_company_id_position_id_unique UNIQUE (company_id, position_id);


--
-- Name: salary_bands salary_bands_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_bands
    ADD CONSTRAINT salary_bands_pkey PRIMARY KEY (id);


--
-- Name: salary_records salary_records_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_records
    ADD CONSTRAINT salary_records_pkey PRIMARY KEY (id);


--
-- Name: salary_review_items salary_review_items_period_id_employee_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_review_items
    ADD CONSTRAINT salary_review_items_period_id_employee_id_unique UNIQUE (period_id, employee_id);


--
-- Name: salary_review_items salary_review_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_review_items
    ADD CONSTRAINT salary_review_items_pkey PRIMARY KEY (id);


--
-- Name: salary_review_periods salary_review_periods_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_review_periods
    ADD CONSTRAINT salary_review_periods_pkey PRIMARY KEY (id);


--
-- Name: saved_reports saved_reports_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.saved_reports
    ADD CONSTRAINT saved_reports_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: setting_values setting_values_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.setting_values
    ADD CONSTRAINT setting_values_pkey PRIMARY KEY (id);


--
-- Name: shifts shifts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shifts
    ADD CONSTRAINT shifts_pkey PRIMARY KEY (id);


--
-- Name: software_license_assignments software_license_assignments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.software_license_assignments
    ADD CONSTRAINT software_license_assignments_pkey PRIMARY KEY (id);


--
-- Name: software_licenses software_licenses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.software_licenses
    ADD CONSTRAINT software_licenses_pkey PRIMARY KEY (id);


--
-- Name: survey_questions survey_questions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_questions
    ADD CONSTRAINT survey_questions_pkey PRIMARY KEY (id);


--
-- Name: survey_responses survey_responses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_responses
    ADD CONSTRAINT survey_responses_pkey PRIMARY KEY (id);


--
-- Name: survey_submissions survey_submissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_submissions
    ADD CONSTRAINT survey_submissions_pkey PRIMARY KEY (id);


--
-- Name: surveys surveys_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.surveys
    ADD CONSTRAINT surveys_pkey PRIMARY KEY (id);


--
-- Name: telescope_entries telescope_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telescope_entries
    ADD CONSTRAINT telescope_entries_pkey PRIMARY KEY (sequence);


--
-- Name: telescope_entries_tags telescope_entries_tags_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telescope_entries_tags
    ADD CONSTRAINT telescope_entries_tags_pkey PRIMARY KEY (entry_uuid, tag);


--
-- Name: telescope_entries telescope_entries_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telescope_entries
    ADD CONSTRAINT telescope_entries_uuid_unique UNIQUE (uuid);


--
-- Name: telescope_monitoring telescope_monitoring_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telescope_monitoring
    ADD CONSTRAINT telescope_monitoring_pkey PRIMARY KEY (tag);


--
-- Name: timesheets timesheets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.timesheets
    ADD CONSTRAINT timesheets_pkey PRIMARY KEY (id);


--
-- Name: timesheets timesheets_user_id_period_start_period_end_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.timesheets
    ADD CONSTRAINT timesheets_user_id_period_start_period_end_unique UNIQUE (user_id, period_start, period_end);


--
-- Name: training_certificates training_certificates_certificate_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.training_certificates
    ADD CONSTRAINT training_certificates_certificate_number_unique UNIQUE (certificate_number);


--
-- Name: training_certificates training_certificates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.training_certificates
    ADD CONSTRAINT training_certificates_pkey PRIMARY KEY (id);


--
-- Name: training_participants training_participants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.training_participants
    ADD CONSTRAINT training_participants_pkey PRIMARY KEY (id);


--
-- Name: training_participants training_participants_session_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.training_participants
    ADD CONSTRAINT training_participants_session_id_user_id_unique UNIQUE (session_id, user_id);


--
-- Name: training_requests training_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.training_requests
    ADD CONSTRAINT training_requests_pkey PRIMARY KEY (id);


--
-- Name: training_sessions training_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.training_sessions
    ADD CONSTRAINT training_sessions_pkey PRIMARY KEY (id);


--
-- Name: trainings trainings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trainings
    ADD CONSTRAINT trainings_pkey PRIMARY KEY (id);


--
-- Name: user_competencies user_competencies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_competencies
    ADD CONSTRAINT user_competencies_pkey PRIMARY KEY (id);


--
-- Name: user_competencies user_competencies_user_id_competency_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_competencies
    ADD CONSTRAINT user_competencies_user_id_competency_id_unique UNIQUE (user_id, competency_id);


--
-- Name: user_document_status user_document_status_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_document_status
    ADD CONSTRAINT user_document_status_pkey PRIMARY KEY (id);


--
-- Name: user_document_status user_document_status_user_id_required_document_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_document_status
    ADD CONSTRAINT user_document_status_user_id_required_document_id_unique UNIQUE (user_id, required_document_id);


--
-- Name: user_learning_paths user_learning_paths_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_learning_paths
    ADD CONSTRAINT user_learning_paths_pkey PRIMARY KEY (id);


--
-- Name: user_learning_paths user_learning_paths_user_id_learning_path_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_learning_paths
    ADD CONSTRAINT user_learning_paths_user_id_learning_path_id_unique UNIQUE (user_id, learning_path_id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: webhook_logs webhook_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_logs
    ADD CONSTRAINT webhook_logs_pkey PRIMARY KEY (id);


--
-- Name: webhooks webhooks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhooks
    ADD CONSTRAINT webhooks_pkey PRIMARY KEY (id);


--
-- Name: work_schedules work_schedules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_schedules
    ADD CONSTRAINT work_schedules_pkey PRIMARY KEY (id);


--
-- Name: accrual_logs_company_id_user_id_leave_type_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX accrual_logs_company_id_user_id_leave_type_id_index ON public.accrual_logs USING btree (company_id, user_id, leave_type_id);


--
-- Name: accrual_logs_effective_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX accrual_logs_effective_date_index ON public.accrual_logs USING btree (effective_date);


--
-- Name: accrual_logs_reference_type_reference_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX accrual_logs_reference_type_reference_id_index ON public.accrual_logs USING btree (reference_type, reference_id);


--
-- Name: accrual_policies_company_id_leave_type_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX accrual_policies_company_id_leave_type_id_index ON public.accrual_policies USING btree (company_id, leave_type_id);


--
-- Name: activity_logs_action_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_logs_action_index ON public.activity_logs USING btree (action);


--
-- Name: activity_logs_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_logs_company_id_index ON public.activity_logs USING btree (company_id);


--
-- Name: activity_logs_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_logs_created_at_index ON public.activity_logs USING btree (created_at);


--
-- Name: activity_logs_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_logs_model_type_index ON public.activity_logs USING btree (model_type);


--
-- Name: activity_logs_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_logs_user_id_index ON public.activity_logs USING btree (user_id);


--
-- Name: announcements_company_id_is_published_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX announcements_company_id_is_published_index ON public.announcements USING btree (company_id, is_published);


--
-- Name: announcements_company_id_published_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX announcements_company_id_published_at_index ON public.announcements USING btree (company_id, published_at);


--
-- Name: announcements_company_id_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX announcements_company_id_type_index ON public.announcements USING btree (company_id, type);


--
-- Name: api_keys_company_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX api_keys_company_id_is_active_index ON public.api_keys USING btree (company_id, is_active);


--
-- Name: application_status_logs_job_application_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX application_status_logs_job_application_id_index ON public.application_status_logs USING btree (job_application_id);


--
-- Name: approval_delegations_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX approval_delegations_idx ON public.approval_delegations USING btree (company_id, delegator_id, start_date, end_date);


--
-- Name: approval_escalation_alerts_company_id_alert_level_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX approval_escalation_alerts_company_id_alert_level_index ON public.approval_escalation_alerts USING btree (company_id, alert_level);


--
-- Name: approval_instances_approvable_type_approvable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX approval_instances_approvable_type_approvable_id_index ON public.approval_instances USING btree (approvable_type, approvable_id);


--
-- Name: approval_instances_company_id_approval_workflow_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX approval_instances_company_id_approval_workflow_id_index ON public.approval_instances USING btree (company_id, approval_workflow_id);


--
-- Name: approval_instances_company_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX approval_instances_company_id_status_index ON public.approval_instances USING btree (company_id, status);


--
-- Name: approval_records_approvable_type_approvable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX approval_records_approvable_type_approvable_id_index ON public.approval_records USING btree (approvable_type, approvable_id);


--
-- Name: approval_records_company_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX approval_records_company_id_status_index ON public.approval_records USING btree (company_id, status);


--
-- Name: approval_steps_approval_workflow_id_step_order_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX approval_steps_approval_workflow_id_step_order_index ON public.approval_steps USING btree (approval_workflow_id, step_order);


--
-- Name: approval_workflows_company_id_entity_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX approval_workflows_company_id_entity_type_index ON public.approval_workflows USING btree (company_id, entity_type);


--
-- Name: asset_assignments_asset_id_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX asset_assignments_asset_id_user_id_index ON public.asset_assignments USING btree (asset_id, user_id);


--
-- Name: asset_requests_company_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX asset_requests_company_id_status_index ON public.asset_requests USING btree (company_id, status);


--
-- Name: assets_company_id_asset_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX assets_company_id_asset_code_index ON public.assets USING btree (company_id, asset_code);


--
-- Name: attendance_kiosk_tokens_company_id_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX attendance_kiosk_tokens_company_id_expires_at_index ON public.attendance_kiosk_tokens USING btree (company_id, expires_at);


--
-- Name: attendance_kiosk_tokens_company_id_used_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX attendance_kiosk_tokens_company_id_used_at_index ON public.attendance_kiosk_tokens USING btree (company_id, used_at);


--
-- Name: attendance_records_company_date_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX attendance_records_company_date_idx ON public.attendance_records USING btree (company_id, date);


--
-- Name: attendance_records_company_id_branch_id_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX attendance_records_company_id_branch_id_date_index ON public.attendance_records USING btree (company_id, branch_id, date);


--
-- Name: attendance_records_company_id_source_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX attendance_records_company_id_source_index ON public.attendance_records USING btree (company_id, source);


--
-- Name: attendance_records_company_status_date_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX attendance_records_company_status_date_idx ON public.attendance_records USING btree (company_id, status, date);


--
-- Name: branches_company_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX branches_company_id_is_active_index ON public.branches USING btree (company_id, is_active);


--
-- Name: branches_company_id_is_headquarters_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX branches_company_id_is_headquarters_index ON public.branches USING btree (company_id, is_headquarters);


--
-- Name: buddy_assignments_company_id_buddy_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX buddy_assignments_company_id_buddy_id_index ON public.buddy_assignments USING btree (company_id, buddy_id);


--
-- Name: cfd_company_entity_field_key_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX cfd_company_entity_field_key_unique ON public.custom_field_definitions USING btree (company_id, entity_type, field_key) WHERE ((company_id IS NOT NULL) AND (deleted_at IS NULL));


--
-- Name: cfd_entity_system_key_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cfd_entity_system_key_idx ON public.custom_field_definitions USING btree (entity_type, is_system, system_key);


--
-- Name: cfd_system_entity_field_key_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX cfd_system_entity_field_key_unique ON public.custom_field_definitions USING btree (entity_type, field_key) WHERE ((company_id IS NULL) AND (deleted_at IS NULL));


--
-- Name: companies_organization_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX companies_organization_id_index ON public.companies USING btree (organization_id);


--
-- Name: companies_package_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX companies_package_type_index ON public.companies USING btree (package_type);


--
-- Name: companies_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX companies_status_index ON public.companies USING btree (status);


--
-- Name: company_ledger_due_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_ledger_due_date_index ON public.company_ledger USING btree (due_date);


--
-- Name: company_ledger_payment_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_ledger_payment_date_index ON public.company_ledger USING btree (payment_date);


--
-- Name: company_ledger_reference_type_reference_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_ledger_reference_type_reference_id_index ON public.company_ledger USING btree (reference_type, reference_id);


--
-- Name: company_ledger_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_ledger_type_index ON public.company_ledger USING btree (type);


--
-- Name: company_user_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_user_company_id_index ON public.company_user USING btree (company_id);


--
-- Name: company_user_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_user_user_id_index ON public.company_user USING btree (user_id);


--
-- Name: consent_records_company_id_consent_type_granted_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX consent_records_company_id_consent_type_granted_index ON public.consent_records USING btree (company_id, consent_type, granted);


--
-- Name: consent_records_company_id_notice_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX consent_records_company_id_notice_id_index ON public.consent_records USING btree (company_id, notice_id);


--
-- Name: consent_records_company_id_subject_type_subject_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX consent_records_company_id_subject_type_subject_id_index ON public.consent_records USING btree (company_id, subject_type, subject_id);


--
-- Name: continuous_feedbacks_company_id_to_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX continuous_feedbacks_company_id_to_user_id_index ON public.continuous_feedbacks USING btree (company_id, to_user_id);


--
-- Name: continuous_feedbacks_related_type_related_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX continuous_feedbacks_related_type_related_id_index ON public.continuous_feedbacks USING btree (related_type, related_id);


--
-- Name: custom_field_definitions_company_id_entity_type_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX custom_field_definitions_company_id_entity_type_is_active_index ON public.custom_field_definitions USING btree (company_id, entity_type, is_active);


--
-- Name: dashboard_shares_company_id_dashboard_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dashboard_shares_company_id_dashboard_id_index ON public.dashboard_shares USING btree (company_id, dashboard_id);


--
-- Name: dashboard_shares_dashboard_id_department_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dashboard_shares_dashboard_id_department_id_index ON public.dashboard_shares USING btree (dashboard_id, department_id);


--
-- Name: dashboard_shares_dashboard_id_role_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dashboard_shares_dashboard_id_role_id_index ON public.dashboard_shares USING btree (dashboard_id, role_id);


--
-- Name: dashboard_shares_dashboard_id_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dashboard_shares_dashboard_id_user_id_index ON public.dashboard_shares USING btree (dashboard_id, user_id);


--
-- Name: dashboards_company_id_folder_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dashboards_company_id_folder_id_index ON public.dashboards USING btree (company_id, folder_id);


--
-- Name: dashboards_company_id_is_system_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dashboards_company_id_is_system_index ON public.dashboards USING btree (company_id, is_system);


--
-- Name: dashboards_company_id_owner_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dashboards_company_id_owner_id_index ON public.dashboards USING btree (company_id, owner_id);


--
-- Name: dashboards_module_key_is_system_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dashboards_module_key_is_system_index ON public.dashboards USING btree (module_key, is_system);


--
-- Name: dashboards_system_key_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX dashboards_system_key_unique ON public.dashboards USING btree (system_key) WHERE ((company_id IS NULL) AND (system_key IS NOT NULL) AND (is_system = true));


--
-- Name: data_breaches_company_id_detected_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX data_breaches_company_id_detected_at_index ON public.data_breaches USING btree (company_id, detected_at);


--
-- Name: data_breaches_company_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX data_breaches_company_id_status_index ON public.data_breaches USING btree (company_id, status);


--
-- Name: data_processing_activities_company_id_data_subject_group_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX data_processing_activities_company_id_data_subject_group_index ON public.data_processing_activities USING btree (company_id, data_subject_group);


--
-- Name: data_subject_export_packages_company_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX data_subject_export_packages_company_id_status_index ON public.data_subject_export_packages USING btree (company_id, status);


--
-- Name: data_subject_export_packages_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX data_subject_export_packages_expires_at_index ON public.data_subject_export_packages USING btree (expires_at);


--
-- Name: data_subject_requests_company_id_due_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX data_subject_requests_company_id_due_date_index ON public.data_subject_requests USING btree (company_id, due_date);


--
-- Name: data_subject_requests_company_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX data_subject_requests_company_id_status_index ON public.data_subject_requests USING btree (company_id, status);


--
-- Name: data_subject_requests_company_id_subject_type_subject_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX data_subject_requests_company_id_subject_type_subject_id_index ON public.data_subject_requests USING btree (company_id, subject_type, subject_id);


--
-- Name: departments_company_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX departments_company_id_is_active_index ON public.departments USING btree (company_id, is_active);


--
-- Name: destruction_candidates_company_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX destruction_candidates_company_id_status_index ON public.destruction_candidates USING btree (company_id, status);


--
-- Name: destruction_candidates_company_id_subject_type_subject_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX destruction_candidates_company_id_subject_type_subject_id_index ON public.destruction_candidates USING btree (company_id, subject_type, subject_id);


--
-- Name: destruction_logs_company_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX destruction_logs_company_id_created_at_index ON public.destruction_logs USING btree (company_id, created_at);


--
-- Name: document_expiry_alerts_company_id_threshold_days_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX document_expiry_alerts_company_id_threshold_days_index ON public.document_expiry_alerts USING btree (company_id, threshold_days);


--
-- Name: documents_company_id_category_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX documents_company_id_category_id_index ON public.documents USING btree (company_id, category_id);


--
-- Name: documents_company_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX documents_company_id_created_at_index ON public.documents USING btree (company_id, created_at);


--
-- Name: employee_dashboards_company_id_is_shared_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employee_dashboards_company_id_is_shared_index ON public.employee_dashboards USING btree (company_id, is_shared);


--
-- Name: employee_dashboards_company_id_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employee_dashboards_company_id_user_id_index ON public.employee_dashboards USING btree (company_id, user_id);


--
-- Name: employee_dashboards_is_favorite_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employee_dashboards_is_favorite_index ON public.employee_dashboards USING btree (is_favorite);


--
-- Name: employee_documents_company_id_employee_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employee_documents_company_id_employee_id_index ON public.employee_documents USING btree (company_id, employee_id);


--
-- Name: employee_documents_employee_id_category_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employee_documents_employee_id_category_index ON public.employee_documents USING btree (employee_id, category);


--
-- Name: employee_documents_employee_id_is_visible_to_employee_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employee_documents_employee_id_is_visible_to_employee_index ON public.employee_documents USING btree (employee_id, is_visible_to_employee);


--
-- Name: employee_requests_company_id_request_type_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employee_requests_company_id_request_type_id_index ON public.employee_requests USING btree (company_id, request_type_id);


--
-- Name: employee_requests_company_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employee_requests_company_id_status_index ON public.employee_requests USING btree (company_id, status);


--
-- Name: employee_requests_employee_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employee_requests_employee_id_status_index ON public.employee_requests USING btree (employee_id, status);


--
-- Name: employees_company_department_status_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employees_company_department_status_idx ON public.employees USING btree (company_id, department_id, status);


--
-- Name: employees_company_id_branch_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employees_company_id_branch_id_index ON public.employees USING btree (company_id, branch_id);


--
-- Name: employees_company_id_department_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employees_company_id_department_id_index ON public.employees USING btree (company_id, department_id);


--
-- Name: employees_company_id_position_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employees_company_id_position_id_index ON public.employees USING btree (company_id, position_id);


--
-- Name: employees_company_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employees_company_id_status_index ON public.employees USING btree (company_id, status);


--
-- Name: employees_company_id_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employees_company_id_user_id_index ON public.employees USING btree (company_id, user_id);


--
-- Name: form_definitions_company_entity_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX form_definitions_company_entity_unique ON public.form_definitions USING btree (company_id, entity_type) WHERE ((company_id IS NOT NULL) AND (deleted_at IS NULL));


--
-- Name: form_definitions_company_id_entity_type_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX form_definitions_company_id_entity_type_is_active_index ON public.form_definitions USING btree (company_id, entity_type, is_active);


--
-- Name: form_definitions_system_entity_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX form_definitions_system_entity_unique ON public.form_definitions USING btree (entity_type) WHERE ((company_id IS NULL) AND (deleted_at IS NULL));


--
-- Name: holidays_company_id_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX holidays_company_id_date_index ON public.holidays USING btree (company_id, date);


--
-- Name: holidays_country_code_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX holidays_country_code_date_index ON public.holidays USING btree (country_code, date);


--
-- Name: interviews_company_id_job_application_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX interviews_company_id_job_application_id_index ON public.interviews USING btree (company_id, job_application_id);


--
-- Name: job_applications_company_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX job_applications_company_id_status_index ON public.job_applications USING btree (company_id, status);


--
-- Name: job_applications_job_position_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX job_applications_job_position_id_status_index ON public.job_applications USING btree (job_position_id, status);


--
-- Name: job_offers_company_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX job_offers_company_id_status_index ON public.job_offers USING btree (company_id, status);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: key_results_objective_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX key_results_objective_id_status_index ON public.key_results USING btree (objective_id, status);


--
-- Name: leave_requests_company_id_user_id_start_date_end_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX leave_requests_company_id_user_id_start_date_end_date_index ON public.leave_requests USING btree (company_id, user_id, start_date, end_date);


--
-- Name: leave_requests_company_status_dates_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX leave_requests_company_status_dates_idx ON public.leave_requests USING btree (company_id, status, start_date, end_date);


--
-- Name: leave_types_company_id_system_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX leave_types_company_id_system_code_index ON public.leave_types USING btree (company_id, system_code);


--
-- Name: legal_holds_company_id_subject_type_subject_id_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX legal_holds_company_id_subject_type_subject_id_active_index ON public.legal_holds USING btree (company_id, subject_type, subject_id, active);


--
-- Name: license_packages_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX license_packages_is_active_index ON public.license_packages USING btree (is_active);


--
-- Name: license_packages_sort_order_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX license_packages_sort_order_index ON public.license_packages USING btree (sort_order);


--
-- Name: lookups_company_id_lookup_type_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX lookups_company_id_lookup_type_is_active_index ON public.lookups USING btree (company_id, lookup_type, is_active);


--
-- Name: lookups_company_type_value_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX lookups_company_type_value_unique ON public.lookups USING btree (company_id, lookup_type, value) WHERE ((company_id IS NOT NULL) AND (deleted_at IS NULL));


--
-- Name: lookups_lookup_type_value_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX lookups_lookup_type_value_index ON public.lookups USING btree (lookup_type, value);


--
-- Name: lookups_system_type_value_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX lookups_system_type_value_unique ON public.lookups USING btree (lookup_type, value) WHERE ((company_id IS NULL) AND (deleted_at IS NULL));


--
-- Name: mandatory_trainings_company_id_scope_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mandatory_trainings_company_id_scope_index ON public.mandatory_trainings USING btree (company_id, scope);


--
-- Name: model_has_permissions_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_permissions_model_id_model_type_index ON public.model_has_permissions USING btree (model_id, model_type);


--
-- Name: model_has_roles_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_roles_model_id_model_type_index ON public.model_has_roles USING btree (model_id, model_type);


--
-- Name: notification_templates_company_id_event_key_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_templates_company_id_event_key_index ON public.notification_templates USING btree (company_id, event_key);


--
-- Name: notifications_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_company_id_index ON public.notifications USING btree (company_id);


--
-- Name: notifications_notifiable_type_notifiable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_notifiable_type_notifiable_id_index ON public.notifications USING btree (notifiable_type, notifiable_id);


--
-- Name: objectives_company_id_level_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX objectives_company_id_level_index ON public.objectives USING btree (company_id, level);


--
-- Name: objectives_company_id_owner_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX objectives_company_id_owner_id_index ON public.objectives USING btree (company_id, owner_id);


--
-- Name: onboarding_processes_company_id_process_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX onboarding_processes_company_id_process_type_index ON public.onboarding_processes USING btree (company_id, process_type);


--
-- Name: onboarding_tasks_process_id_order_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX onboarding_tasks_process_id_order_index ON public.onboarding_tasks USING btree (process_id, "order");


--
-- Name: onboarding_templates_company_id_process_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX onboarding_templates_company_id_process_type_index ON public.onboarding_templates USING btree (company_id, process_type);


--
-- Name: one_on_one_meetings_company_id_manager_id_employee_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX one_on_one_meetings_company_id_manager_id_employee_id_index ON public.one_on_one_meetings USING btree (company_id, manager_id, employee_id);


--
-- Name: payslips_company_id_is_published_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payslips_company_id_is_published_index ON public.payslips USING btree (company_id, is_published);


--
-- Name: payslips_company_id_period_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payslips_company_id_period_index ON public.payslips USING btree (company_id, period);


--
-- Name: personal_access_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: positions_company_id_department_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX positions_company_id_department_id_index ON public.positions USING btree (company_id, department_id);


--
-- Name: positions_company_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX positions_company_id_is_active_index ON public.positions USING btree (company_id, is_active);


--
-- Name: positions_company_id_sgk_occupation_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX positions_company_id_sgk_occupation_code_index ON public.positions USING btree (company_id, sgk_occupation_code);


--
-- Name: preboarding_tokens_token_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX preboarding_tokens_token_is_active_index ON public.preboarding_tokens USING btree (token, is_active);


--
-- Name: privacy_notices_company_id_audience_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX privacy_notices_company_id_audience_is_active_index ON public.privacy_notices USING btree (company_id, audience, is_active);


--
-- Name: report_access_logs_company_id_contains_sensitive_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX report_access_logs_company_id_contains_sensitive_index ON public.report_access_logs USING btree (company_id, contains_sensitive);


--
-- Name: report_access_logs_company_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX report_access_logs_company_id_created_at_index ON public.report_access_logs USING btree (company_id, created_at);


--
-- Name: report_access_logs_company_id_dashboard_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX report_access_logs_company_id_dashboard_id_index ON public.report_access_logs USING btree (company_id, dashboard_id);


--
-- Name: report_access_logs_company_id_report_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX report_access_logs_company_id_report_id_index ON public.report_access_logs USING btree (company_id, report_id);


--
-- Name: report_access_logs_company_id_user_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX report_access_logs_company_id_user_id_created_at_index ON public.report_access_logs USING btree (company_id, user_id, created_at);


--
-- Name: report_folders_company_id_parent_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX report_folders_company_id_parent_id_index ON public.report_folders USING btree (company_id, parent_id);


--
-- Name: report_folders_company_id_sort_order_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX report_folders_company_id_sort_order_index ON public.report_folders USING btree (company_id, sort_order);


--
-- Name: report_measures_company_id_dataset_key_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX report_measures_company_id_dataset_key_index ON public.report_measures USING btree (company_id, dataset_key);


--
-- Name: report_schedules_company_id_active_next_run_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX report_schedules_company_id_active_next_run_at_index ON public.report_schedules USING btree (company_id, active, next_run_at);


--
-- Name: report_schedules_company_id_owner_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX report_schedules_company_id_owner_id_index ON public.report_schedules USING btree (company_id, owner_id);


--
-- Name: report_shares_company_id_saved_report_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX report_shares_company_id_saved_report_id_index ON public.report_shares USING btree (company_id, saved_report_id);


--
-- Name: report_shares_saved_report_id_department_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX report_shares_saved_report_id_department_id_index ON public.report_shares USING btree (saved_report_id, department_id);


--
-- Name: report_shares_saved_report_id_role_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX report_shares_saved_report_id_role_id_index ON public.report_shares USING btree (saved_report_id, role_id);


--
-- Name: report_shares_saved_report_id_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX report_shares_saved_report_id_user_id_index ON public.report_shares USING btree (saved_report_id, user_id);


--
-- Name: required_documents_company_id_scope_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX required_documents_company_id_scope_index ON public.required_documents USING btree (company_id, scope);


--
-- Name: retention_decisions_company_id_subject_type_subject_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX retention_decisions_company_id_subject_type_subject_id_index ON public.retention_decisions USING btree (company_id, subject_type, subject_id);


--
-- Name: retention_policies_company_id_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX retention_policies_company_id_active_index ON public.retention_policies USING btree (company_id, active);


--
-- Name: retention_policies_company_id_data_category_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX retention_policies_company_id_data_category_index ON public.retention_policies USING btree (company_id, data_category);


--
-- Name: salary_bands_company_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX salary_bands_company_id_is_active_index ON public.salary_bands USING btree (company_id, is_active);


--
-- Name: salary_records_company_id_change_reason_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX salary_records_company_id_change_reason_index ON public.salary_records USING btree (company_id, change_reason);


--
-- Name: salary_records_company_id_employee_id_effective_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX salary_records_company_id_employee_id_effective_date_index ON public.salary_records USING btree (company_id, employee_id, effective_date);


--
-- Name: salary_review_items_company_id_period_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX salary_review_items_company_id_period_id_index ON public.salary_review_items USING btree (company_id, period_id);


--
-- Name: salary_review_periods_company_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX salary_review_periods_company_id_status_index ON public.salary_review_periods USING btree (company_id, status);


--
-- Name: saved_reports_company_id_dataset_key_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX saved_reports_company_id_dataset_key_index ON public.saved_reports USING btree (company_id, dataset_key);


--
-- Name: saved_reports_company_id_folder_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX saved_reports_company_id_folder_id_index ON public.saved_reports USING btree (company_id, folder_id);


--
-- Name: saved_reports_company_id_is_shared_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX saved_reports_company_id_is_shared_index ON public.saved_reports USING btree (company_id, is_shared);


--
-- Name: saved_reports_company_id_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX saved_reports_company_id_user_id_index ON public.saved_reports USING btree (company_id, user_id);


--
-- Name: saved_reports_is_favorite_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX saved_reports_is_favorite_index ON public.saved_reports USING btree (is_favorite);


--
-- Name: saved_reports_module_key_is_system_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX saved_reports_module_key_is_system_index ON public.saved_reports USING btree (module_key, is_system);


--
-- Name: saved_reports_share_role_ids_gin; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX saved_reports_share_role_ids_gin ON public.saved_reports USING gin (share_role_ids jsonb_path_ops);


--
-- Name: saved_reports_share_user_ids_gin; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX saved_reports_share_user_ids_gin ON public.saved_reports USING gin (share_user_ids jsonb_path_ops);


--
-- Name: saved_reports_system_key_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX saved_reports_system_key_unique ON public.saved_reports USING btree (system_key) WHERE ((company_id IS NULL) AND (system_key IS NOT NULL) AND (is_system = true));


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: setting_values_company_id_key_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX setting_values_company_id_key_index ON public.setting_values USING btree (company_id, key);


--
-- Name: setting_values_company_id_scope_type_key_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX setting_values_company_id_scope_type_key_index ON public.setting_values USING btree (company_id, scope_type, key);


--
-- Name: setting_values_scope_key_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX setting_values_scope_key_unique ON public.setting_values USING btree (COALESCE(company_id, (0)::bigint), scope_type, COALESCE(scope_id, (0)::bigint), key) WHERE (deleted_at IS NULL);


--
-- Name: setting_values_scope_type_scope_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX setting_values_scope_type_scope_id_index ON public.setting_values USING btree (scope_type, scope_id);


--
-- Name: software_license_assignments_software_license_id_is_active_inde; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX software_license_assignments_software_license_id_is_active_inde ON public.software_license_assignments USING btree (software_license_id, is_active);


--
-- Name: software_licenses_company_id_expiry_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX software_licenses_company_id_expiry_date_index ON public.software_licenses USING btree (company_id, expiry_date);


--
-- Name: survey_submissions_survey_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX survey_submissions_survey_id_status_index ON public.survey_submissions USING btree (survey_id, status);


--
-- Name: telescope_entries_batch_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telescope_entries_batch_id_index ON public.telescope_entries USING btree (batch_id);


--
-- Name: telescope_entries_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telescope_entries_created_at_index ON public.telescope_entries USING btree (created_at);


--
-- Name: telescope_entries_family_hash_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telescope_entries_family_hash_index ON public.telescope_entries USING btree (family_hash);


--
-- Name: telescope_entries_tags_tag_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telescope_entries_tags_tag_index ON public.telescope_entries_tags USING btree (tag);


--
-- Name: telescope_entries_type_should_display_on_index_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telescope_entries_type_should_display_on_index_index ON public.telescope_entries USING btree (type, should_display_on_index);


--
-- Name: users_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_company_id_index ON public.users USING btree (company_id);


--
-- Name: users_invitation_token_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_invitation_token_index ON public.users USING btree (invitation_token);


--
-- Name: users_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_is_active_index ON public.users USING btree (is_active);


--
-- Name: users_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_type_index ON public.users USING btree (type);


--
-- Name: webhook_logs_is_successful_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX webhook_logs_is_successful_index ON public.webhook_logs USING btree (is_successful);


--
-- Name: webhook_logs_triggered_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX webhook_logs_triggered_at_index ON public.webhook_logs USING btree (triggered_at);


--
-- Name: webhook_logs_webhook_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX webhook_logs_webhook_id_index ON public.webhook_logs USING btree (webhook_id);


--
-- Name: webhooks_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX webhooks_company_id_index ON public.webhooks USING btree (company_id);


--
-- Name: webhooks_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX webhooks_is_active_index ON public.webhooks USING btree (is_active);


--
-- Name: accrual_logs accrual_logs_accrual_policy_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accrual_logs
    ADD CONSTRAINT accrual_logs_accrual_policy_id_foreign FOREIGN KEY (accrual_policy_id) REFERENCES public.accrual_policies(id) ON DELETE SET NULL;


--
-- Name: accrual_logs accrual_logs_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accrual_logs
    ADD CONSTRAINT accrual_logs_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: accrual_logs accrual_logs_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accrual_logs
    ADD CONSTRAINT accrual_logs_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: accrual_logs accrual_logs_leave_balance_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accrual_logs
    ADD CONSTRAINT accrual_logs_leave_balance_id_foreign FOREIGN KEY (leave_balance_id) REFERENCES public.leave_balances(id) ON DELETE SET NULL;


--
-- Name: accrual_logs accrual_logs_leave_type_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accrual_logs
    ADD CONSTRAINT accrual_logs_leave_type_id_foreign FOREIGN KEY (leave_type_id) REFERENCES public.leave_types(id) ON DELETE CASCADE;


--
-- Name: accrual_logs accrual_logs_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accrual_logs
    ADD CONSTRAINT accrual_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: accrual_policies accrual_policies_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accrual_policies
    ADD CONSTRAINT accrual_policies_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: accrual_policies accrual_policies_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accrual_policies
    ADD CONSTRAINT accrual_policies_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: accrual_policies accrual_policies_leave_type_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accrual_policies
    ADD CONSTRAINT accrual_policies_leave_type_id_foreign FOREIGN KEY (leave_type_id) REFERENCES public.leave_types(id) ON DELETE CASCADE;


--
-- Name: accrual_policies accrual_policies_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accrual_policies
    ADD CONSTRAINT accrual_policies_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: activity_logs activity_logs_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_logs
    ADD CONSTRAINT activity_logs_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE SET NULL;


--
-- Name: activity_logs activity_logs_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_logs
    ADD CONSTRAINT activity_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: announcement_reads announcement_reads_announcement_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.announcement_reads
    ADD CONSTRAINT announcement_reads_announcement_id_foreign FOREIGN KEY (announcement_id) REFERENCES public.announcements(id) ON DELETE CASCADE;


--
-- Name: announcement_reads announcement_reads_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.announcement_reads
    ADD CONSTRAINT announcement_reads_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: announcement_reads announcement_reads_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.announcement_reads
    ADD CONSTRAINT announcement_reads_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: announcements announcements_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.announcements
    ADD CONSTRAINT announcements_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: announcements announcements_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.announcements
    ADD CONSTRAINT announcements_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: announcements announcements_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.announcements
    ADD CONSTRAINT announcements_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: api_keys api_keys_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.api_keys
    ADD CONSTRAINT api_keys_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: api_keys api_keys_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.api_keys
    ADD CONSTRAINT api_keys_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: application_forms application_forms_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.application_forms
    ADD CONSTRAINT application_forms_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: application_forms application_forms_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.application_forms
    ADD CONSTRAINT application_forms_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: application_forms application_forms_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.application_forms
    ADD CONSTRAINT application_forms_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: application_sources application_sources_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.application_sources
    ADD CONSTRAINT application_sources_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: application_status_logs application_status_logs_changed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.application_status_logs
    ADD CONSTRAINT application_status_logs_changed_by_foreign FOREIGN KEY (changed_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: application_status_logs application_status_logs_job_application_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.application_status_logs
    ADD CONSTRAINT application_status_logs_job_application_id_foreign FOREIGN KEY (job_application_id) REFERENCES public.job_applications(id) ON DELETE CASCADE;


--
-- Name: approval_delegations approval_delegations_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_delegations
    ADD CONSTRAINT approval_delegations_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: approval_delegations approval_delegations_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_delegations
    ADD CONSTRAINT approval_delegations_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: approval_delegations approval_delegations_delegate_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_delegations
    ADD CONSTRAINT approval_delegations_delegate_id_foreign FOREIGN KEY (delegate_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: approval_delegations approval_delegations_delegator_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_delegations
    ADD CONSTRAINT approval_delegations_delegator_id_foreign FOREIGN KEY (delegator_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: approval_escalation_alerts approval_escalation_alerts_approval_record_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_escalation_alerts
    ADD CONSTRAINT approval_escalation_alerts_approval_record_id_foreign FOREIGN KEY (approval_record_id) REFERENCES public.approval_records(id) ON DELETE CASCADE;


--
-- Name: approval_escalation_alerts approval_escalation_alerts_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_escalation_alerts
    ADD CONSTRAINT approval_escalation_alerts_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: approval_instances approval_instances_approval_workflow_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_instances
    ADD CONSTRAINT approval_instances_approval_workflow_id_foreign FOREIGN KEY (approval_workflow_id) REFERENCES public.approval_workflows(id) ON DELETE CASCADE;


--
-- Name: approval_instances approval_instances_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_instances
    ADD CONSTRAINT approval_instances_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: approval_records approval_records_approval_instance_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_records
    ADD CONSTRAINT approval_records_approval_instance_id_foreign FOREIGN KEY (approval_instance_id) REFERENCES public.approval_instances(id) ON DELETE SET NULL;


--
-- Name: approval_records approval_records_approval_step_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_records
    ADD CONSTRAINT approval_records_approval_step_id_foreign FOREIGN KEY (approval_step_id) REFERENCES public.approval_steps(id) ON DELETE CASCADE;


--
-- Name: approval_records approval_records_approval_workflow_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_records
    ADD CONSTRAINT approval_records_approval_workflow_id_foreign FOREIGN KEY (approval_workflow_id) REFERENCES public.approval_workflows(id) ON DELETE CASCADE;


--
-- Name: approval_records approval_records_approver_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_records
    ADD CONSTRAINT approval_records_approver_id_foreign FOREIGN KEY (approver_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: approval_records approval_records_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_records
    ADD CONSTRAINT approval_records_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: approval_records approval_records_escalated_to_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_records
    ADD CONSTRAINT approval_records_escalated_to_foreign FOREIGN KEY (escalated_to) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: approval_steps approval_steps_approval_workflow_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_steps
    ADD CONSTRAINT approval_steps_approval_workflow_id_foreign FOREIGN KEY (approval_workflow_id) REFERENCES public.approval_workflows(id) ON DELETE CASCADE;


--
-- Name: approval_steps approval_steps_specific_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_steps
    ADD CONSTRAINT approval_steps_specific_user_id_foreign FOREIGN KEY (specific_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: approval_workflows approval_workflows_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_workflows
    ADD CONSTRAINT approval_workflows_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: approval_workflows approval_workflows_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_workflows
    ADD CONSTRAINT approval_workflows_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: approval_workflows approval_workflows_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_workflows
    ADD CONSTRAINT approval_workflows_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: asset_assignments asset_assignments_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_assignments
    ADD CONSTRAINT asset_assignments_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id) ON DELETE CASCADE;


--
-- Name: asset_assignments asset_assignments_assigned_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_assignments
    ADD CONSTRAINT asset_assignments_assigned_by_foreign FOREIGN KEY (assigned_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: asset_assignments asset_assignments_returned_to_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_assignments
    ADD CONSTRAINT asset_assignments_returned_to_foreign FOREIGN KEY (returned_to) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: asset_assignments asset_assignments_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_assignments
    ADD CONSTRAINT asset_assignments_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: asset_categories asset_categories_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_categories
    ADD CONSTRAINT asset_categories_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: asset_maintenance asset_maintenance_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_maintenance
    ADD CONSTRAINT asset_maintenance_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id) ON DELETE CASCADE;


--
-- Name: asset_maintenance asset_maintenance_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_maintenance
    ADD CONSTRAINT asset_maintenance_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: asset_requests asset_requests_approval_workflow_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_requests
    ADD CONSTRAINT asset_requests_approval_workflow_id_foreign FOREIGN KEY (approval_workflow_id) REFERENCES public.approval_workflows(id) ON DELETE SET NULL;


--
-- Name: asset_requests asset_requests_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_requests
    ADD CONSTRAINT asset_requests_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: asset_requests asset_requests_asset_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_requests
    ADD CONSTRAINT asset_requests_asset_category_id_foreign FOREIGN KEY (asset_category_id) REFERENCES public.asset_categories(id) ON DELETE SET NULL;


--
-- Name: asset_requests asset_requests_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_requests
    ADD CONSTRAINT asset_requests_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: asset_requests asset_requests_fulfilled_with_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_requests
    ADD CONSTRAINT asset_requests_fulfilled_with_asset_id_foreign FOREIGN KEY (fulfilled_with_asset_id) REFERENCES public.assets(id) ON DELETE SET NULL;


--
-- Name: asset_requests asset_requests_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_requests
    ADD CONSTRAINT asset_requests_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: assets assets_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assets
    ADD CONSTRAINT assets_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.asset_categories(id) ON DELETE CASCADE;


--
-- Name: assets assets_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assets
    ADD CONSTRAINT assets_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: assets assets_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assets
    ADD CONSTRAINT assets_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: attendance_kiosk_tokens attendance_kiosk_tokens_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_kiosk_tokens
    ADD CONSTRAINT attendance_kiosk_tokens_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE SET NULL;


--
-- Name: attendance_kiosk_tokens attendance_kiosk_tokens_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_kiosk_tokens
    ADD CONSTRAINT attendance_kiosk_tokens_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: attendance_kiosk_tokens attendance_kiosk_tokens_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_kiosk_tokens
    ADD CONSTRAINT attendance_kiosk_tokens_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: attendance_kiosk_tokens attendance_kiosk_tokens_used_by_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_kiosk_tokens
    ADD CONSTRAINT attendance_kiosk_tokens_used_by_user_id_foreign FOREIGN KEY (used_by_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: attendance_records attendance_records_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_records
    ADD CONSTRAINT attendance_records_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: attendance_records attendance_records_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_records
    ADD CONSTRAINT attendance_records_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE SET NULL;


--
-- Name: attendance_records attendance_records_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_records
    ADD CONSTRAINT attendance_records_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: attendance_records attendance_records_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_records
    ADD CONSTRAINT attendance_records_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: branches branches_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: branches branches_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: branches branches_manager_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_manager_id_foreign FOREIGN KEY (manager_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: branches branches_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: buddy_assignments buddy_assignments_assigned_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buddy_assignments
    ADD CONSTRAINT buddy_assignments_assigned_by_foreign FOREIGN KEY (assigned_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: buddy_assignments buddy_assignments_buddy_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buddy_assignments
    ADD CONSTRAINT buddy_assignments_buddy_id_foreign FOREIGN KEY (buddy_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: buddy_assignments buddy_assignments_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buddy_assignments
    ADD CONSTRAINT buddy_assignments_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: buddy_assignments buddy_assignments_new_hire_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buddy_assignments
    ADD CONSTRAINT buddy_assignments_new_hire_id_foreign FOREIGN KEY (new_hire_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: buddy_assignments buddy_assignments_onboarding_process_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buddy_assignments
    ADD CONSTRAINT buddy_assignments_onboarding_process_id_foreign FOREIGN KEY (onboarding_process_id) REFERENCES public.onboarding_processes(id) ON DELETE CASCADE;


--
-- Name: buddy_pool buddy_pool_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buddy_pool
    ADD CONSTRAINT buddy_pool_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: buddy_pool buddy_pool_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buddy_pool
    ADD CONSTRAINT buddy_pool_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: candidate_scores candidate_scores_job_application_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.candidate_scores
    ADD CONSTRAINT candidate_scores_job_application_id_foreign FOREIGN KEY (job_application_id) REFERENCES public.job_applications(id) ON DELETE CASCADE;


--
-- Name: candidate_scores candidate_scores_job_position_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.candidate_scores
    ADD CONSTRAINT candidate_scores_job_position_id_foreign FOREIGN KEY (job_position_id) REFERENCES public.job_positions(id) ON DELETE CASCADE;


--
-- Name: companies companies_license_package_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_license_package_id_foreign FOREIGN KEY (license_package_id) REFERENCES public.license_packages(id) ON DELETE SET NULL;


--
-- Name: companies companies_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE SET NULL;


--
-- Name: company_ledger company_ledger_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_ledger
    ADD CONSTRAINT company_ledger_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: company_modules company_modules_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_modules
    ADD CONSTRAINT company_modules_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: company_modules company_modules_module_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_modules
    ADD CONSTRAINT company_modules_module_id_foreign FOREIGN KEY (module_id) REFERENCES public.modules(id) ON DELETE CASCADE;


--
-- Name: company_user company_user_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_user
    ADD CONSTRAINT company_user_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: company_user company_user_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_user
    ADD CONSTRAINT company_user_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE SET NULL;


--
-- Name: company_user company_user_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_user
    ADD CONSTRAINT company_user_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: competencies competencies_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.competencies
    ADD CONSTRAINT competencies_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: competencies competencies_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.competencies
    ADD CONSTRAINT competencies_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: competencies competencies_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.competencies
    ADD CONSTRAINT competencies_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: consent_records consent_records_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consent_records
    ADD CONSTRAINT consent_records_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: consent_records consent_records_notice_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consent_records
    ADD CONSTRAINT consent_records_notice_id_foreign FOREIGN KEY (notice_id) REFERENCES public.privacy_notices(id) ON DELETE SET NULL;


--
-- Name: continuous_feedbacks continuous_feedbacks_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.continuous_feedbacks
    ADD CONSTRAINT continuous_feedbacks_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: continuous_feedbacks continuous_feedbacks_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.continuous_feedbacks
    ADD CONSTRAINT continuous_feedbacks_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: continuous_feedbacks continuous_feedbacks_from_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.continuous_feedbacks
    ADD CONSTRAINT continuous_feedbacks_from_user_id_foreign FOREIGN KEY (from_user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: continuous_feedbacks continuous_feedbacks_to_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.continuous_feedbacks
    ADD CONSTRAINT continuous_feedbacks_to_user_id_foreign FOREIGN KEY (to_user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: custom_field_definitions custom_field_definitions_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.custom_field_definitions
    ADD CONSTRAINT custom_field_definitions_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE SET NULL;


--
-- Name: custom_field_definitions custom_field_definitions_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.custom_field_definitions
    ADD CONSTRAINT custom_field_definitions_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: custom_field_definitions custom_field_definitions_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.custom_field_definitions
    ADD CONSTRAINT custom_field_definitions_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: dashboard_shares dashboard_shares_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dashboard_shares
    ADD CONSTRAINT dashboard_shares_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: dashboard_shares dashboard_shares_dashboard_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dashboard_shares
    ADD CONSTRAINT dashboard_shares_dashboard_id_foreign FOREIGN KEY (dashboard_id) REFERENCES public.dashboards(id) ON DELETE CASCADE;


--
-- Name: dashboard_shares dashboard_shares_department_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dashboard_shares
    ADD CONSTRAINT dashboard_shares_department_id_foreign FOREIGN KEY (department_id) REFERENCES public.departments(id) ON DELETE CASCADE;


--
-- Name: dashboard_shares dashboard_shares_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dashboard_shares
    ADD CONSTRAINT dashboard_shares_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: dashboard_shares dashboard_shares_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dashboard_shares
    ADD CONSTRAINT dashboard_shares_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: dashboards dashboards_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dashboards
    ADD CONSTRAINT dashboards_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE SET NULL;


--
-- Name: dashboards dashboards_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dashboards
    ADD CONSTRAINT dashboards_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: dashboards dashboards_folder_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dashboards
    ADD CONSTRAINT dashboards_folder_id_foreign FOREIGN KEY (folder_id) REFERENCES public.report_folders(id) ON DELETE SET NULL;


--
-- Name: dashboards dashboards_owner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dashboards
    ADD CONSTRAINT dashboards_owner_id_foreign FOREIGN KEY (owner_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: data_breaches data_breaches_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_breaches
    ADD CONSTRAINT data_breaches_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: data_processing_activities data_processing_activities_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_processing_activities
    ADD CONSTRAINT data_processing_activities_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: data_subject_export_access_logs data_subject_export_access_logs_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_subject_export_access_logs
    ADD CONSTRAINT data_subject_export_access_logs_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: data_subject_export_access_logs data_subject_export_access_logs_export_package_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_subject_export_access_logs
    ADD CONSTRAINT data_subject_export_access_logs_export_package_id_foreign FOREIGN KEY (export_package_id) REFERENCES public.data_subject_export_packages(id) ON DELETE CASCADE;


--
-- Name: data_subject_export_access_logs data_subject_export_access_logs_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_subject_export_access_logs
    ADD CONSTRAINT data_subject_export_access_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: data_subject_export_packages data_subject_export_packages_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_subject_export_packages
    ADD CONSTRAINT data_subject_export_packages_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: data_subject_export_packages data_subject_export_packages_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_subject_export_packages
    ADD CONSTRAINT data_subject_export_packages_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: data_subject_export_packages data_subject_export_packages_data_subject_request_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_subject_export_packages
    ADD CONSTRAINT data_subject_export_packages_data_subject_request_id_foreign FOREIGN KEY (data_subject_request_id) REFERENCES public.data_subject_requests(id) ON DELETE CASCADE;


--
-- Name: data_subject_requests data_subject_requests_assigned_to_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_subject_requests
    ADD CONSTRAINT data_subject_requests_assigned_to_foreign FOREIGN KEY (assigned_to) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: data_subject_requests data_subject_requests_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_subject_requests
    ADD CONSTRAINT data_subject_requests_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: data_subject_requests data_subject_requests_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_subject_requests
    ADD CONSTRAINT data_subject_requests_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: data_subject_requests data_subject_requests_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_subject_requests
    ADD CONSTRAINT data_subject_requests_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: data_subject_requests data_subject_requests_verified_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_subject_requests
    ADD CONSTRAINT data_subject_requests_verified_by_foreign FOREIGN KEY (verified_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: departments departments_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: departments departments_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: departments departments_manager_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_manager_id_foreign FOREIGN KEY (manager_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: departments departments_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.departments(id) ON DELETE SET NULL;


--
-- Name: departments departments_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: destruction_approvals destruction_approvals_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.destruction_approvals
    ADD CONSTRAINT destruction_approvals_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: destruction_approvals destruction_approvals_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.destruction_approvals
    ADD CONSTRAINT destruction_approvals_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: destruction_candidates destruction_candidates_approval_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.destruction_candidates
    ADD CONSTRAINT destruction_candidates_approval_id_foreign FOREIGN KEY (approval_id) REFERENCES public.destruction_approvals(id) ON DELETE SET NULL;


--
-- Name: destruction_candidates destruction_candidates_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.destruction_candidates
    ADD CONSTRAINT destruction_candidates_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: destruction_candidates destruction_candidates_retention_policy_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.destruction_candidates
    ADD CONSTRAINT destruction_candidates_retention_policy_id_foreign FOREIGN KEY (retention_policy_id) REFERENCES public.retention_policies(id) ON DELETE SET NULL;


--
-- Name: destruction_logs destruction_logs_approval_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.destruction_logs
    ADD CONSTRAINT destruction_logs_approval_id_foreign FOREIGN KEY (approval_id) REFERENCES public.destruction_approvals(id) ON DELETE SET NULL;


--
-- Name: destruction_logs destruction_logs_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.destruction_logs
    ADD CONSTRAINT destruction_logs_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: destruction_logs destruction_logs_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.destruction_logs
    ADD CONSTRAINT destruction_logs_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: destruction_logs destruction_logs_destruction_candidate_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.destruction_logs
    ADD CONSTRAINT destruction_logs_destruction_candidate_id_foreign FOREIGN KEY (destruction_candidate_id) REFERENCES public.destruction_candidates(id) ON DELETE SET NULL;


--
-- Name: destruction_logs destruction_logs_retention_policy_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.destruction_logs
    ADD CONSTRAINT destruction_logs_retention_policy_id_foreign FOREIGN KEY (retention_policy_id) REFERENCES public.retention_policies(id) ON DELETE SET NULL;


--
-- Name: document_approvals document_approvals_approver_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_approvals
    ADD CONSTRAINT document_approvals_approver_id_foreign FOREIGN KEY (approver_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: document_approvals document_approvals_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_approvals
    ADD CONSTRAINT document_approvals_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: document_categories document_categories_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_categories
    ADD CONSTRAINT document_categories_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: document_categories document_categories_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_categories
    ADD CONSTRAINT document_categories_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: document_categories document_categories_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_categories
    ADD CONSTRAINT document_categories_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: document_expiry_alerts document_expiry_alerts_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_expiry_alerts
    ADD CONSTRAINT document_expiry_alerts_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: document_expiry_alerts document_expiry_alerts_employee_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_expiry_alerts
    ADD CONSTRAINT document_expiry_alerts_employee_document_id_foreign FOREIGN KEY (employee_document_id) REFERENCES public.employee_documents(id) ON DELETE CASCADE;


--
-- Name: document_versions document_versions_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_versions
    ADD CONSTRAINT document_versions_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: document_versions document_versions_uploaded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_versions
    ADD CONSTRAINT document_versions_uploaded_by_foreign FOREIGN KEY (uploaded_by) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: documents documents_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.document_categories(id) ON DELETE SET NULL;


--
-- Name: documents documents_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: documents documents_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: documents documents_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: documents documents_uploaded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_uploaded_by_foreign FOREIGN KEY (uploaded_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: employee_dashboards employee_dashboards_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_dashboards
    ADD CONSTRAINT employee_dashboards_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: employee_dashboards employee_dashboards_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_dashboards
    ADD CONSTRAINT employee_dashboards_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: employee_documents employee_documents_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_documents
    ADD CONSTRAINT employee_documents_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: employee_documents employee_documents_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_documents
    ADD CONSTRAINT employee_documents_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: employee_documents employee_documents_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_documents
    ADD CONSTRAINT employee_documents_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: employee_documents employee_documents_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_documents
    ADD CONSTRAINT employee_documents_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: employee_documents employee_documents_uploaded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_documents
    ADD CONSTRAINT employee_documents_uploaded_by_foreign FOREIGN KEY (uploaded_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: employee_request_history employee_request_history_changed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_request_history
    ADD CONSTRAINT employee_request_history_changed_by_foreign FOREIGN KEY (changed_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: employee_request_history employee_request_history_employee_request_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_request_history
    ADD CONSTRAINT employee_request_history_employee_request_id_foreign FOREIGN KEY (employee_request_id) REFERENCES public.employee_requests(id) ON DELETE CASCADE;


--
-- Name: employee_requests employee_requests_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_requests
    ADD CONSTRAINT employee_requests_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: employee_requests employee_requests_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_requests
    ADD CONSTRAINT employee_requests_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: employee_requests employee_requests_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_requests
    ADD CONSTRAINT employee_requests_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: employee_requests employee_requests_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_requests
    ADD CONSTRAINT employee_requests_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: employee_requests employee_requests_request_type_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_requests
    ADD CONSTRAINT employee_requests_request_type_id_foreign FOREIGN KEY (request_type_id) REFERENCES public.request_types(id) ON DELETE CASCADE;


--
-- Name: employee_requests employee_requests_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_requests
    ADD CONSTRAINT employee_requests_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: employee_shifts employee_shifts_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_shifts
    ADD CONSTRAINT employee_shifts_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: employee_shifts employee_shifts_shift_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_shifts
    ADD CONSTRAINT employee_shifts_shift_id_foreign FOREIGN KEY (shift_id) REFERENCES public.shifts(id) ON DELETE CASCADE;


--
-- Name: employee_shifts employee_shifts_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_shifts
    ADD CONSTRAINT employee_shifts_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: employees employees_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE SET NULL;


--
-- Name: employees employees_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: employees employees_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: employees employees_department_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_department_id_foreign FOREIGN KEY (department_id) REFERENCES public.departments(id) ON DELETE SET NULL;


--
-- Name: employees employees_manager_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_manager_id_foreign FOREIGN KEY (manager_id) REFERENCES public.employees(id) ON DELETE SET NULL;


--
-- Name: employees employees_position_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_position_id_foreign FOREIGN KEY (position_id) REFERENCES public.positions(id) ON DELETE SET NULL;


--
-- Name: employees employees_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: employees employees_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: enps_records enps_records_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.enps_records
    ADD CONSTRAINT enps_records_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: enps_records enps_records_survey_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.enps_records
    ADD CONSTRAINT enps_records_survey_id_foreign FOREIGN KEY (survey_id) REFERENCES public.surveys(id) ON DELETE SET NULL;


--
-- Name: expense_categories expense_categories_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_categories
    ADD CONSTRAINT expense_categories_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: expense_claims expense_claims_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_claims
    ADD CONSTRAINT expense_claims_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: expense_claims expense_claims_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_claims
    ADD CONSTRAINT expense_claims_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: expense_claims expense_claims_paid_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_claims
    ADD CONSTRAINT expense_claims_paid_by_foreign FOREIGN KEY (paid_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: expense_claims expense_claims_submitted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_claims
    ADD CONSTRAINT expense_claims_submitted_by_foreign FOREIGN KEY (submitted_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: expense_claims expense_claims_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_claims
    ADD CONSTRAINT expense_claims_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: expense_items expense_items_expense_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_items
    ADD CONSTRAINT expense_items_expense_category_id_foreign FOREIGN KEY (expense_category_id) REFERENCES public.expense_categories(id) ON DELETE CASCADE;


--
-- Name: expense_items expense_items_expense_claim_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_items
    ADD CONSTRAINT expense_items_expense_claim_id_foreign FOREIGN KEY (expense_claim_id) REFERENCES public.expense_claims(id) ON DELETE CASCADE;


--
-- Name: feedback_providers feedback_providers_performance_review_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feedback_providers
    ADD CONSTRAINT feedback_providers_performance_review_id_foreign FOREIGN KEY (performance_review_id) REFERENCES public.performance_reviews(id) ON DELETE CASCADE;


--
-- Name: feedback_providers feedback_providers_provider_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feedback_providers
    ADD CONSTRAINT feedback_providers_provider_id_foreign FOREIGN KEY (provider_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: feedback_responses feedback_responses_feedback_provider_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feedback_responses
    ADD CONSTRAINT feedback_responses_feedback_provider_id_foreign FOREIGN KEY (feedback_provider_id) REFERENCES public.feedback_providers(id) ON DELETE CASCADE;


--
-- Name: feedback_responses feedback_responses_performance_criteria_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feedback_responses
    ADD CONSTRAINT feedback_responses_performance_criteria_id_foreign FOREIGN KEY (performance_criteria_id) REFERENCES public.performance_criteria(id) ON DELETE CASCADE;


--
-- Name: form_definitions form_definitions_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.form_definitions
    ADD CONSTRAINT form_definitions_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE SET NULL;


--
-- Name: form_definitions form_definitions_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.form_definitions
    ADD CONSTRAINT form_definitions_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: form_definitions form_definitions_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.form_definitions
    ADD CONSTRAINT form_definitions_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: holidays holidays_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.holidays
    ADD CONSTRAINT holidays_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: holidays holidays_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.holidays
    ADD CONSTRAINT holidays_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: holidays holidays_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.holidays
    ADD CONSTRAINT holidays_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: interview_scorecards interview_scorecards_interview_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.interview_scorecards
    ADD CONSTRAINT interview_scorecards_interview_id_foreign FOREIGN KEY (interview_id) REFERENCES public.interviews(id) ON DELETE CASCADE;


--
-- Name: interviews interviews_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.interviews
    ADD CONSTRAINT interviews_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: interviews interviews_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.interviews
    ADD CONSTRAINT interviews_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: interviews interviews_interviewer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.interviews
    ADD CONSTRAINT interviews_interviewer_id_foreign FOREIGN KEY (interviewer_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: interviews interviews_job_application_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.interviews
    ADD CONSTRAINT interviews_job_application_id_foreign FOREIGN KEY (job_application_id) REFERENCES public.job_applications(id) ON DELETE CASCADE;


--
-- Name: interviews interviews_job_position_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.interviews
    ADD CONSTRAINT interviews_job_position_id_foreign FOREIGN KEY (job_position_id) REFERENCES public.job_positions(id) ON DELETE CASCADE;


--
-- Name: job_applications job_applications_assigned_to_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_applications
    ADD CONSTRAINT job_applications_assigned_to_foreign FOREIGN KEY (assigned_to) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: job_applications job_applications_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_applications
    ADD CONSTRAINT job_applications_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: job_applications job_applications_converted_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_applications
    ADD CONSTRAINT job_applications_converted_employee_id_foreign FOREIGN KEY (converted_employee_id) REFERENCES public.employees(id) ON DELETE SET NULL;


--
-- Name: job_applications job_applications_job_position_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_applications
    ADD CONSTRAINT job_applications_job_position_id_foreign FOREIGN KEY (job_position_id) REFERENCES public.job_positions(id) ON DELETE CASCADE;


--
-- Name: job_applications job_applications_source_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_applications
    ADD CONSTRAINT job_applications_source_id_foreign FOREIGN KEY (source_id) REFERENCES public.application_sources(id) ON DELETE SET NULL;


--
-- Name: job_applications job_applications_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_applications
    ADD CONSTRAINT job_applications_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: job_offers job_offers_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_offers
    ADD CONSTRAINT job_offers_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: job_offers job_offers_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_offers
    ADD CONSTRAINT job_offers_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: job_offers job_offers_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_offers
    ADD CONSTRAINT job_offers_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: job_offers job_offers_job_application_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_offers
    ADD CONSTRAINT job_offers_job_application_id_foreign FOREIGN KEY (job_application_id) REFERENCES public.job_applications(id) ON DELETE CASCADE;


--
-- Name: job_offers job_offers_job_position_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_offers
    ADD CONSTRAINT job_offers_job_position_id_foreign FOREIGN KEY (job_position_id) REFERENCES public.job_positions(id) ON DELETE CASCADE;


--
-- Name: job_positions job_positions_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_positions
    ADD CONSTRAINT job_positions_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: job_positions job_positions_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_positions
    ADD CONSTRAINT job_positions_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: job_positions job_positions_form_definition_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_positions
    ADD CONSTRAINT job_positions_form_definition_id_foreign FOREIGN KEY (form_definition_id) REFERENCES public.form_definitions(id) ON DELETE SET NULL;


--
-- Name: job_positions job_positions_form_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_positions
    ADD CONSTRAINT job_positions_form_id_foreign FOREIGN KEY (form_id) REFERENCES public.application_forms(id) ON DELETE SET NULL;


--
-- Name: job_positions job_positions_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_positions
    ADD CONSTRAINT job_positions_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: key_result_updates key_result_updates_key_result_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.key_result_updates
    ADD CONSTRAINT key_result_updates_key_result_id_foreign FOREIGN KEY (key_result_id) REFERENCES public.key_results(id) ON DELETE CASCADE;


--
-- Name: key_result_updates key_result_updates_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.key_result_updates
    ADD CONSTRAINT key_result_updates_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: key_results key_results_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.key_results
    ADD CONSTRAINT key_results_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: key_results key_results_objective_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.key_results
    ADD CONSTRAINT key_results_objective_id_foreign FOREIGN KEY (objective_id) REFERENCES public.objectives(id) ON DELETE CASCADE;


--
-- Name: key_results key_results_owner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.key_results
    ADD CONSTRAINT key_results_owner_id_foreign FOREIGN KEY (owner_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: key_results key_results_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.key_results
    ADD CONSTRAINT key_results_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: learning_path_items learning_path_items_learning_path_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.learning_path_items
    ADD CONSTRAINT learning_path_items_learning_path_id_foreign FOREIGN KEY (learning_path_id) REFERENCES public.learning_paths(id) ON DELETE CASCADE;


--
-- Name: learning_path_items learning_path_items_prerequisite_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.learning_path_items
    ADD CONSTRAINT learning_path_items_prerequisite_item_id_foreign FOREIGN KEY (prerequisite_item_id) REFERENCES public.learning_path_items(id) ON DELETE SET NULL;


--
-- Name: learning_path_items learning_path_items_training_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.learning_path_items
    ADD CONSTRAINT learning_path_items_training_id_foreign FOREIGN KEY (training_id) REFERENCES public.trainings(id) ON DELETE CASCADE;


--
-- Name: learning_paths learning_paths_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.learning_paths
    ADD CONSTRAINT learning_paths_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: learning_paths learning_paths_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.learning_paths
    ADD CONSTRAINT learning_paths_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: learning_paths learning_paths_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.learning_paths
    ADD CONSTRAINT learning_paths_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: leave_balances leave_balances_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_balances
    ADD CONSTRAINT leave_balances_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: leave_balances leave_balances_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_balances
    ADD CONSTRAINT leave_balances_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: leave_balances leave_balances_leave_type_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_balances
    ADD CONSTRAINT leave_balances_leave_type_id_foreign FOREIGN KEY (leave_type_id) REFERENCES public.leave_types(id) ON DELETE CASCADE;


--
-- Name: leave_balances leave_balances_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_balances
    ADD CONSTRAINT leave_balances_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: leave_balances leave_balances_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_balances
    ADD CONSTRAINT leave_balances_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: leave_requests leave_requests_approval_workflow_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_requests
    ADD CONSTRAINT leave_requests_approval_workflow_id_foreign FOREIGN KEY (approval_workflow_id) REFERENCES public.approval_workflows(id) ON DELETE SET NULL;


--
-- Name: leave_requests leave_requests_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_requests
    ADD CONSTRAINT leave_requests_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: leave_requests leave_requests_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_requests
    ADD CONSTRAINT leave_requests_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: leave_requests leave_requests_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_requests
    ADD CONSTRAINT leave_requests_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: leave_requests leave_requests_leave_type_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_requests
    ADD CONSTRAINT leave_requests_leave_type_id_foreign FOREIGN KEY (leave_type_id) REFERENCES public.leave_types(id) ON DELETE CASCADE;


--
-- Name: leave_requests leave_requests_rejected_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_requests
    ADD CONSTRAINT leave_requests_rejected_by_foreign FOREIGN KEY (rejected_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: leave_requests leave_requests_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_requests
    ADD CONSTRAINT leave_requests_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: leave_requests leave_requests_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_requests
    ADD CONSTRAINT leave_requests_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: leave_types leave_types_accrual_policy_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_types
    ADD CONSTRAINT leave_types_accrual_policy_id_foreign FOREIGN KEY (accrual_policy_id) REFERENCES public.accrual_policies(id) ON DELETE SET NULL;


--
-- Name: leave_types leave_types_approval_workflow_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_types
    ADD CONSTRAINT leave_types_approval_workflow_id_foreign FOREIGN KEY (approval_workflow_id) REFERENCES public.approval_workflows(id) ON DELETE SET NULL;


--
-- Name: leave_types leave_types_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_types
    ADD CONSTRAINT leave_types_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: leave_types leave_types_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_types
    ADD CONSTRAINT leave_types_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: leave_types leave_types_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_types
    ADD CONSTRAINT leave_types_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: legal_holds legal_holds_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.legal_holds
    ADD CONSTRAINT legal_holds_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: legal_holds legal_holds_placed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.legal_holds
    ADD CONSTRAINT legal_holds_placed_by_foreign FOREIGN KEY (placed_by) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: legal_holds legal_holds_released_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.legal_holds
    ADD CONSTRAINT legal_holds_released_by_foreign FOREIGN KEY (released_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: license_package_modules license_package_modules_license_package_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.license_package_modules
    ADD CONSTRAINT license_package_modules_license_package_id_foreign FOREIGN KEY (license_package_id) REFERENCES public.license_packages(id) ON DELETE CASCADE;


--
-- Name: lookups lookups_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lookups
    ADD CONSTRAINT lookups_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE SET NULL;


--
-- Name: lookups lookups_parent_lookup_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lookups
    ADD CONSTRAINT lookups_parent_lookup_id_foreign FOREIGN KEY (parent_lookup_id) REFERENCES public.lookups(id) ON DELETE SET NULL;


--
-- Name: mandatory_trainings mandatory_trainings_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mandatory_trainings
    ADD CONSTRAINT mandatory_trainings_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: mandatory_trainings mandatory_trainings_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mandatory_trainings
    ADD CONSTRAINT mandatory_trainings_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: mandatory_trainings mandatory_trainings_training_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mandatory_trainings
    ADD CONSTRAINT mandatory_trainings_training_id_foreign FOREIGN KEY (training_id) REFERENCES public.trainings(id) ON DELETE CASCADE;


--
-- Name: milestone_completions milestone_completions_completed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.milestone_completions
    ADD CONSTRAINT milestone_completions_completed_by_foreign FOREIGN KEY (completed_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: milestone_completions milestone_completions_onboarding_milestone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.milestone_completions
    ADD CONSTRAINT milestone_completions_onboarding_milestone_id_foreign FOREIGN KEY (onboarding_milestone_id) REFERENCES public.onboarding_milestones(id) ON DELETE CASCADE;


--
-- Name: milestone_completions milestone_completions_onboarding_process_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.milestone_completions
    ADD CONSTRAINT milestone_completions_onboarding_process_id_foreign FOREIGN KEY (onboarding_process_id) REFERENCES public.onboarding_processes(id) ON DELETE CASCADE;


--
-- Name: milestone_completions milestone_completions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.milestone_completions
    ADD CONSTRAINT milestone_completions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: model_has_permissions model_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: model_has_roles model_has_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: notification_templates notification_templates_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_templates
    ADD CONSTRAINT notification_templates_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: notification_templates notification_templates_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_templates
    ADD CONSTRAINT notification_templates_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: notification_templates notification_templates_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_templates
    ADD CONSTRAINT notification_templates_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: notifications notifications_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: objectives objectives_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.objectives
    ADD CONSTRAINT objectives_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: objectives objectives_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.objectives
    ADD CONSTRAINT objectives_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: objectives objectives_department_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.objectives
    ADD CONSTRAINT objectives_department_id_foreign FOREIGN KEY (department_id) REFERENCES public.departments(id) ON DELETE SET NULL;


--
-- Name: objectives objectives_owner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.objectives
    ADD CONSTRAINT objectives_owner_id_foreign FOREIGN KEY (owner_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: objectives objectives_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.objectives
    ADD CONSTRAINT objectives_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.objectives(id) ON DELETE CASCADE;


--
-- Name: objectives objectives_performance_period_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.objectives
    ADD CONSTRAINT objectives_performance_period_id_foreign FOREIGN KEY (performance_period_id) REFERENCES public.performance_periods(id) ON DELETE SET NULL;


--
-- Name: objectives objectives_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.objectives
    ADD CONSTRAINT objectives_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: onboarding_milestones onboarding_milestones_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_milestones
    ADD CONSTRAINT onboarding_milestones_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: onboarding_milestones onboarding_milestones_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_milestones
    ADD CONSTRAINT onboarding_milestones_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: onboarding_milestones onboarding_milestones_onboarding_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_milestones
    ADD CONSTRAINT onboarding_milestones_onboarding_template_id_foreign FOREIGN KEY (onboarding_template_id) REFERENCES public.onboarding_templates(id) ON DELETE CASCADE;


--
-- Name: onboarding_processes onboarding_processes_assigned_to_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_processes
    ADD CONSTRAINT onboarding_processes_assigned_to_foreign FOREIGN KEY (assigned_to) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: onboarding_processes onboarding_processes_buddy_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_processes
    ADD CONSTRAINT onboarding_processes_buddy_id_foreign FOREIGN KEY (buddy_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: onboarding_processes onboarding_processes_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_processes
    ADD CONSTRAINT onboarding_processes_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: onboarding_processes onboarding_processes_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_processes
    ADD CONSTRAINT onboarding_processes_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: onboarding_processes onboarding_processes_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_processes
    ADD CONSTRAINT onboarding_processes_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE SET NULL;


--
-- Name: onboarding_processes onboarding_processes_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_processes
    ADD CONSTRAINT onboarding_processes_template_id_foreign FOREIGN KEY (template_id) REFERENCES public.onboarding_templates(id) ON DELETE SET NULL;


--
-- Name: onboarding_processes onboarding_processes_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_processes
    ADD CONSTRAINT onboarding_processes_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: onboarding_processes onboarding_processes_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_processes
    ADD CONSTRAINT onboarding_processes_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: onboarding_surveys onboarding_surveys_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_surveys
    ADD CONSTRAINT onboarding_surveys_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: onboarding_surveys onboarding_surveys_onboarding_process_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_surveys
    ADD CONSTRAINT onboarding_surveys_onboarding_process_id_foreign FOREIGN KEY (onboarding_process_id) REFERENCES public.onboarding_processes(id) ON DELETE CASCADE;


--
-- Name: onboarding_surveys onboarding_surveys_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_surveys
    ADD CONSTRAINT onboarding_surveys_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: onboarding_tasks onboarding_tasks_assigned_to_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_tasks
    ADD CONSTRAINT onboarding_tasks_assigned_to_foreign FOREIGN KEY (assigned_to) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: onboarding_tasks onboarding_tasks_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_tasks
    ADD CONSTRAINT onboarding_tasks_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: onboarding_tasks onboarding_tasks_completed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_tasks
    ADD CONSTRAINT onboarding_tasks_completed_by_foreign FOREIGN KEY (completed_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: onboarding_tasks onboarding_tasks_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_tasks
    ADD CONSTRAINT onboarding_tasks_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: onboarding_tasks onboarding_tasks_process_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_tasks
    ADD CONSTRAINT onboarding_tasks_process_id_foreign FOREIGN KEY (process_id) REFERENCES public.onboarding_processes(id) ON DELETE CASCADE;


--
-- Name: onboarding_tasks onboarding_tasks_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_tasks
    ADD CONSTRAINT onboarding_tasks_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: onboarding_templates onboarding_templates_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_templates
    ADD CONSTRAINT onboarding_templates_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: onboarding_templates onboarding_templates_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_templates
    ADD CONSTRAINT onboarding_templates_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: onboarding_templates onboarding_templates_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onboarding_templates
    ADD CONSTRAINT onboarding_templates_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: one_on_one_meetings one_on_one_meetings_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.one_on_one_meetings
    ADD CONSTRAINT one_on_one_meetings_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: one_on_one_meetings one_on_one_meetings_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.one_on_one_meetings
    ADD CONSTRAINT one_on_one_meetings_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: one_on_one_meetings one_on_one_meetings_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.one_on_one_meetings
    ADD CONSTRAINT one_on_one_meetings_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: one_on_one_meetings one_on_one_meetings_manager_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.one_on_one_meetings
    ADD CONSTRAINT one_on_one_meetings_manager_id_foreign FOREIGN KEY (manager_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: payslips payslips_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payslips
    ADD CONSTRAINT payslips_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: payslips payslips_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payslips
    ADD CONSTRAINT payslips_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: payslips payslips_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payslips
    ADD CONSTRAINT payslips_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: payslips payslips_published_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payslips
    ADD CONSTRAINT payslips_published_by_foreign FOREIGN KEY (published_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: payslips payslips_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payslips
    ADD CONSTRAINT payslips_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: performance_criteria performance_criteria_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_criteria
    ADD CONSTRAINT performance_criteria_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: performance_criteria performance_criteria_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_criteria
    ADD CONSTRAINT performance_criteria_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: performance_periods performance_periods_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_periods
    ADD CONSTRAINT performance_periods_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: performance_periods performance_periods_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_periods
    ADD CONSTRAINT performance_periods_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: performance_reviews performance_reviews_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_reviews
    ADD CONSTRAINT performance_reviews_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: performance_reviews performance_reviews_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_reviews
    ADD CONSTRAINT performance_reviews_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: performance_reviews performance_reviews_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_reviews
    ADD CONSTRAINT performance_reviews_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: performance_reviews performance_reviews_period_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_reviews
    ADD CONSTRAINT performance_reviews_period_id_foreign FOREIGN KEY (period_id) REFERENCES public.performance_periods(id) ON DELETE CASCADE;


--
-- Name: performance_reviews performance_reviews_reviewer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_reviews
    ADD CONSTRAINT performance_reviews_reviewer_id_foreign FOREIGN KEY (reviewer_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: performance_scores performance_scores_criteria_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_scores
    ADD CONSTRAINT performance_scores_criteria_id_foreign FOREIGN KEY (criteria_id) REFERENCES public.performance_criteria(id) ON DELETE CASCADE;


--
-- Name: performance_scores performance_scores_review_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_scores
    ADD CONSTRAINT performance_scores_review_id_foreign FOREIGN KEY (review_id) REFERENCES public.performance_reviews(id) ON DELETE CASCADE;


--
-- Name: position_competencies position_competencies_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.position_competencies
    ADD CONSTRAINT position_competencies_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: position_competencies position_competencies_competency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.position_competencies
    ADD CONSTRAINT position_competencies_competency_id_foreign FOREIGN KEY (competency_id) REFERENCES public.competencies(id) ON DELETE CASCADE;


--
-- Name: positions positions_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.positions
    ADD CONSTRAINT positions_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: positions positions_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.positions
    ADD CONSTRAINT positions_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: positions positions_department_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.positions
    ADD CONSTRAINT positions_department_id_foreign FOREIGN KEY (department_id) REFERENCES public.departments(id) ON DELETE SET NULL;


--
-- Name: positions positions_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.positions
    ADD CONSTRAINT positions_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: preboarding_tokens preboarding_tokens_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.preboarding_tokens
    ADD CONSTRAINT preboarding_tokens_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: preboarding_tokens preboarding_tokens_onboarding_process_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.preboarding_tokens
    ADD CONSTRAINT preboarding_tokens_onboarding_process_id_foreign FOREIGN KEY (onboarding_process_id) REFERENCES public.onboarding_processes(id) ON DELETE CASCADE;


--
-- Name: preboarding_tokens preboarding_tokens_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.preboarding_tokens
    ADD CONSTRAINT preboarding_tokens_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: privacy_notices privacy_notices_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.privacy_notices
    ADD CONSTRAINT privacy_notices_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: privacy_notices privacy_notices_published_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.privacy_notices
    ADD CONSTRAINT privacy_notices_published_by_foreign FOREIGN KEY (published_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: report_access_logs report_access_logs_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_access_logs
    ADD CONSTRAINT report_access_logs_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: report_access_logs report_access_logs_dashboard_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_access_logs
    ADD CONSTRAINT report_access_logs_dashboard_id_foreign FOREIGN KEY (dashboard_id) REFERENCES public.dashboards(id) ON DELETE SET NULL;


--
-- Name: report_access_logs report_access_logs_report_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_access_logs
    ADD CONSTRAINT report_access_logs_report_id_foreign FOREIGN KEY (report_id) REFERENCES public.saved_reports(id) ON DELETE SET NULL;


--
-- Name: report_access_logs report_access_logs_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_access_logs
    ADD CONSTRAINT report_access_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: report_folders report_folders_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_folders
    ADD CONSTRAINT report_folders_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: report_folders report_folders_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_folders
    ADD CONSTRAINT report_folders_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.report_folders(id) ON DELETE SET NULL;


--
-- Name: report_measures report_measures_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_measures
    ADD CONSTRAINT report_measures_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: report_schedules report_schedules_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_schedules
    ADD CONSTRAINT report_schedules_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: report_schedules report_schedules_dashboard_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_schedules
    ADD CONSTRAINT report_schedules_dashboard_id_foreign FOREIGN KEY (dashboard_id) REFERENCES public.dashboards(id) ON DELETE CASCADE;


--
-- Name: report_schedules report_schedules_owner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_schedules
    ADD CONSTRAINT report_schedules_owner_id_foreign FOREIGN KEY (owner_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: report_schedules report_schedules_report_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_schedules
    ADD CONSTRAINT report_schedules_report_id_foreign FOREIGN KEY (report_id) REFERENCES public.saved_reports(id) ON DELETE CASCADE;


--
-- Name: report_shares report_shares_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_shares
    ADD CONSTRAINT report_shares_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: report_shares report_shares_department_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_shares
    ADD CONSTRAINT report_shares_department_id_foreign FOREIGN KEY (department_id) REFERENCES public.departments(id) ON DELETE CASCADE;


--
-- Name: report_shares report_shares_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_shares
    ADD CONSTRAINT report_shares_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: report_shares report_shares_saved_report_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_shares
    ADD CONSTRAINT report_shares_saved_report_id_foreign FOREIGN KEY (saved_report_id) REFERENCES public.saved_reports(id) ON DELETE CASCADE;


--
-- Name: report_shares report_shares_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_shares
    ADD CONSTRAINT report_shares_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: request_types request_types_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.request_types
    ADD CONSTRAINT request_types_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: request_types request_types_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.request_types
    ADD CONSTRAINT request_types_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: required_documents required_documents_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.required_documents
    ADD CONSTRAINT required_documents_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: required_documents required_documents_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.required_documents
    ADD CONSTRAINT required_documents_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: required_documents required_documents_document_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.required_documents
    ADD CONSTRAINT required_documents_document_category_id_foreign FOREIGN KEY (document_category_id) REFERENCES public.document_categories(id) ON DELETE SET NULL;


--
-- Name: retention_decisions retention_decisions_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.retention_decisions
    ADD CONSTRAINT retention_decisions_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: retention_decisions retention_decisions_decided_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.retention_decisions
    ADD CONSTRAINT retention_decisions_decided_by_foreign FOREIGN KEY (decided_by) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: retention_decisions retention_decisions_destruction_candidate_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.retention_decisions
    ADD CONSTRAINT retention_decisions_destruction_candidate_id_foreign FOREIGN KEY (destruction_candidate_id) REFERENCES public.destruction_candidates(id) ON DELETE SET NULL;


--
-- Name: retention_policies retention_policies_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.retention_policies
    ADD CONSTRAINT retention_policies_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: salary_bands salary_bands_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_bands
    ADD CONSTRAINT salary_bands_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: salary_bands salary_bands_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_bands
    ADD CONSTRAINT salary_bands_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: salary_bands salary_bands_position_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_bands
    ADD CONSTRAINT salary_bands_position_id_foreign FOREIGN KEY (position_id) REFERENCES public.positions(id) ON DELETE CASCADE;


--
-- Name: salary_bands salary_bands_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_bands
    ADD CONSTRAINT salary_bands_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: salary_records salary_records_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_records
    ADD CONSTRAINT salary_records_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: salary_records salary_records_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_records
    ADD CONSTRAINT salary_records_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: salary_records salary_records_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_records
    ADD CONSTRAINT salary_records_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: salary_records salary_records_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_records
    ADD CONSTRAINT salary_records_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: salary_review_items salary_review_items_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_review_items
    ADD CONSTRAINT salary_review_items_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: salary_review_items salary_review_items_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_review_items
    ADD CONSTRAINT salary_review_items_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: salary_review_items salary_review_items_period_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_review_items
    ADD CONSTRAINT salary_review_items_period_id_foreign FOREIGN KEY (period_id) REFERENCES public.salary_review_periods(id) ON DELETE CASCADE;


--
-- Name: salary_review_periods salary_review_periods_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_review_periods
    ADD CONSTRAINT salary_review_periods_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: salary_review_periods salary_review_periods_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_review_periods
    ADD CONSTRAINT salary_review_periods_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: salary_review_periods salary_review_periods_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_review_periods
    ADD CONSTRAINT salary_review_periods_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: salary_review_periods salary_review_periods_submitted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_review_periods
    ADD CONSTRAINT salary_review_periods_submitted_by_foreign FOREIGN KEY (submitted_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: salary_review_periods salary_review_periods_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_review_periods
    ADD CONSTRAINT salary_review_periods_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: saved_reports saved_reports_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.saved_reports
    ADD CONSTRAINT saved_reports_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE SET NULL;


--
-- Name: saved_reports saved_reports_folder_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.saved_reports
    ADD CONSTRAINT saved_reports_folder_id_foreign FOREIGN KEY (folder_id) REFERENCES public.report_folders(id) ON DELETE SET NULL;


--
-- Name: saved_reports saved_reports_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.saved_reports
    ADD CONSTRAINT saved_reports_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: setting_values setting_values_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.setting_values
    ADD CONSTRAINT setting_values_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: setting_values setting_values_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.setting_values
    ADD CONSTRAINT setting_values_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: shifts shifts_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shifts
    ADD CONSTRAINT shifts_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: software_license_assignments software_license_assignments_assigned_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.software_license_assignments
    ADD CONSTRAINT software_license_assignments_assigned_by_foreign FOREIGN KEY (assigned_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: software_license_assignments software_license_assignments_software_license_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.software_license_assignments
    ADD CONSTRAINT software_license_assignments_software_license_id_foreign FOREIGN KEY (software_license_id) REFERENCES public.software_licenses(id) ON DELETE CASCADE;


--
-- Name: software_license_assignments software_license_assignments_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.software_license_assignments
    ADD CONSTRAINT software_license_assignments_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: software_licenses software_licenses_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.software_licenses
    ADD CONSTRAINT software_licenses_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: software_licenses software_licenses_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.software_licenses
    ADD CONSTRAINT software_licenses_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: software_licenses software_licenses_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.software_licenses
    ADD CONSTRAINT software_licenses_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: survey_questions survey_questions_survey_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_questions
    ADD CONSTRAINT survey_questions_survey_id_foreign FOREIGN KEY (survey_id) REFERENCES public.surveys(id) ON DELETE CASCADE;


--
-- Name: survey_responses survey_responses_survey_question_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_responses
    ADD CONSTRAINT survey_responses_survey_question_id_foreign FOREIGN KEY (survey_question_id) REFERENCES public.survey_questions(id) ON DELETE CASCADE;


--
-- Name: survey_responses survey_responses_survey_submission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_responses
    ADD CONSTRAINT survey_responses_survey_submission_id_foreign FOREIGN KEY (survey_submission_id) REFERENCES public.survey_submissions(id) ON DELETE CASCADE;


--
-- Name: survey_submissions survey_submissions_survey_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_submissions
    ADD CONSTRAINT survey_submissions_survey_id_foreign FOREIGN KEY (survey_id) REFERENCES public.surveys(id) ON DELETE CASCADE;


--
-- Name: survey_submissions survey_submissions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_submissions
    ADD CONSTRAINT survey_submissions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: surveys surveys_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.surveys
    ADD CONSTRAINT surveys_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: surveys surveys_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.surveys
    ADD CONSTRAINT surveys_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: surveys surveys_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.surveys
    ADD CONSTRAINT surveys_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: telescope_entries_tags telescope_entries_tags_entry_uuid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telescope_entries_tags
    ADD CONSTRAINT telescope_entries_tags_entry_uuid_foreign FOREIGN KEY (entry_uuid) REFERENCES public.telescope_entries(uuid) ON DELETE CASCADE;


--
-- Name: timesheets timesheets_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.timesheets
    ADD CONSTRAINT timesheets_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: timesheets timesheets_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.timesheets
    ADD CONSTRAINT timesheets_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: timesheets timesheets_submitted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.timesheets
    ADD CONSTRAINT timesheets_submitted_by_foreign FOREIGN KEY (submitted_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: timesheets timesheets_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.timesheets
    ADD CONSTRAINT timesheets_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: training_certificates training_certificates_issued_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.training_certificates
    ADD CONSTRAINT training_certificates_issued_by_foreign FOREIGN KEY (issued_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: training_certificates training_certificates_participant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.training_certificates
    ADD CONSTRAINT training_certificates_participant_id_foreign FOREIGN KEY (participant_id) REFERENCES public.training_participants(id) ON DELETE CASCADE;


--
-- Name: training_participants training_participants_session_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.training_participants
    ADD CONSTRAINT training_participants_session_id_foreign FOREIGN KEY (session_id) REFERENCES public.training_sessions(id) ON DELETE CASCADE;


--
-- Name: training_participants training_participants_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.training_participants
    ADD CONSTRAINT training_participants_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: training_requests training_requests_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.training_requests
    ADD CONSTRAINT training_requests_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: training_requests training_requests_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.training_requests
    ADD CONSTRAINT training_requests_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: training_requests training_requests_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.training_requests
    ADD CONSTRAINT training_requests_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: training_sessions training_sessions_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.training_sessions
    ADD CONSTRAINT training_sessions_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: training_sessions training_sessions_training_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.training_sessions
    ADD CONSTRAINT training_sessions_training_id_foreign FOREIGN KEY (training_id) REFERENCES public.trainings(id) ON DELETE CASCADE;


--
-- Name: trainings trainings_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trainings
    ADD CONSTRAINT trainings_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: trainings trainings_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trainings
    ADD CONSTRAINT trainings_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: user_competencies user_competencies_assessed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_competencies
    ADD CONSTRAINT user_competencies_assessed_by_foreign FOREIGN KEY (assessed_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: user_competencies user_competencies_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_competencies
    ADD CONSTRAINT user_competencies_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: user_competencies user_competencies_competency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_competencies
    ADD CONSTRAINT user_competencies_competency_id_foreign FOREIGN KEY (competency_id) REFERENCES public.competencies(id) ON DELETE CASCADE;


--
-- Name: user_competencies user_competencies_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_competencies
    ADD CONSTRAINT user_competencies_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_document_status user_document_status_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_document_status
    ADD CONSTRAINT user_document_status_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: user_document_status user_document_status_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_document_status
    ADD CONSTRAINT user_document_status_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE SET NULL;


--
-- Name: user_document_status user_document_status_required_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_document_status
    ADD CONSTRAINT user_document_status_required_document_id_foreign FOREIGN KEY (required_document_id) REFERENCES public.required_documents(id) ON DELETE CASCADE;


--
-- Name: user_document_status user_document_status_reviewed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_document_status
    ADD CONSTRAINT user_document_status_reviewed_by_foreign FOREIGN KEY (reviewed_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: user_document_status user_document_status_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_document_status
    ADD CONSTRAINT user_document_status_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_learning_paths user_learning_paths_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_learning_paths
    ADD CONSTRAINT user_learning_paths_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: user_learning_paths user_learning_paths_learning_path_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_learning_paths
    ADD CONSTRAINT user_learning_paths_learning_path_id_foreign FOREIGN KEY (learning_path_id) REFERENCES public.learning_paths(id) ON DELETE CASCADE;


--
-- Name: user_learning_paths user_learning_paths_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_learning_paths
    ADD CONSTRAINT user_learning_paths_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: users users_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE SET NULL;


--
-- Name: users users_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: users users_last_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_last_company_id_foreign FOREIGN KEY (last_company_id) REFERENCES public.companies(id) ON DELETE SET NULL;


--
-- Name: users users_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: webhook_logs webhook_logs_webhook_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_logs
    ADD CONSTRAINT webhook_logs_webhook_id_foreign FOREIGN KEY (webhook_id) REFERENCES public.webhooks(id) ON DELETE CASCADE;


--
-- Name: webhooks webhooks_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhooks
    ADD CONSTRAINT webhooks_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: webhooks webhooks_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhooks
    ADD CONSTRAINT webhooks_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: webhooks webhooks_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhooks
    ADD CONSTRAINT webhooks_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: work_schedules work_schedules_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_schedules
    ADD CONSTRAINT work_schedules_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--



--
-- PostgreSQL database dump
--


-- Dumped from database version 16.14
-- Dumped by pg_dump version 16.14

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_00_000001_create_companies_table	1
2	0001_01_00_000002_create_license_packages_table	1
3	0001_01_00_000003_create_license_package_modules_table	1
4	0001_01_00_000004_create_company_ledger_table	1
5	0001_01_00_000005_update_companies_add_package_fields	1
6	0001_01_01_000000_create_users_table	1
7	0001_01_01_000001_create_cache_table	1
8	0001_01_01_000002_create_jobs_table	1
9	0001_01_01_000003_create_modules_table	1
10	0001_01_01_000004_create_activity_logs_table	1
11	0001_01_01_000005_create_notifications_table	1
12	0001_02_01_000000_create_application_forms_table	1
13	0001_02_01_000001_create_job_positions_table	1
14	0001_02_01_000002_create_job_applications_table	1
15	0001_02_01_000003_create_application_status_logs_table	1
16	0001_03_00_000000_create_document_categories_table	1
17	0001_03_01_000000_create_documents_table	1
18	0001_04_01_000000_create_leave_types_table	1
19	0001_04_01_000001_create_leave_balances_table	1
20	0001_04_01_000002_create_leave_requests_table	1
21	0001_05_01_000000_create_onboarding_templates_table	1
22	0001_05_01_000001_create_onboarding_processes_table	1
23	0001_05_01_000002_create_onboarding_tasks_table	1
24	0001_06_01_000000_create_departments_table	1
25	0001_06_02_000000_create_employees_table	1
26	0001_06_03_000000_create_payslips_table	1
27	0001_06_04_000000_create_announcements_table	1
28	0001_06_05_000000_create_employee_requests_table	1
29	0001_06_06_000000_create_employee_documents_table	1
30	0001_07_01_000000_create_performance_periods_table	1
31	0001_07_01_000001_create_performance_criteria_table	1
32	0001_07_01_000002_create_performance_reviews_table	1
33	0001_07_01_000003_create_performance_scores_table	1
34	0001_08_01_000000_create_trainings_table	1
35	0001_08_01_000001_create_training_sessions_table	1
36	0001_08_01_000002_create_training_participants_table	1
37	0001_08_01_000003_create_training_certificates_table	1
38	0001_09_01_000000_create_asset_categories_table	1
39	0001_09_01_000001_create_assets_table	1
40	0001_09_01_000002_create_asset_assignments_table	1
41	0001_09_01_000003_create_asset_maintenance_table	1
42	2024_12_24_000001_create_approval_workflows_table	1
43	2024_12_24_000002_create_accrual_policies_table	1
44	2024_12_24_000003_create_holidays_table	1
45	2024_12_24_000004_create_performance_extended_tables	1
46	2024_12_24_000005_create_recruitment_extended_tables	1
47	2024_12_24_000006_create_document_extended_tables	1
48	2024_12_24_000007_create_onboarding_extended_tables	1
49	2024_12_24_000008_create_training_extended_tables	1
50	2024_12_24_000009_create_asset_extended_tables	1
51	2024_12_24_000010_create_survey_tables	1
52	2024_12_25_000001_create_timesheet_tables	1
53	2024_12_25_000002_create_expense_tables	1
54	2025_01_01_000001_create_branches_table	1
55	2025_01_15_000001_create_api_keys_table	1
56	2025_01_20_000001_add_invitation_fields_to_users_table	1
57	2025_01_21_000001_create_webhooks_table	1
58	2025_01_25_000000_update_employees_table_make_user_id_nullable	1
59	2025_01_25_000001_create_custom_field_definitions_table	1
60	2025_12_23_121747_create_permission_tables	1
61	2025_12_23_121747_create_personal_access_tokens_table	1
62	2025_12_23_121753_create_telescope_entries_table	1
63	2025_12_26_115044_create_saved_reports_table	1
64	2025_12_26_205709_create_employee_dashboards_table	1
65	2026_07_11_000001_add_data_scope_to_roles_table	1
66	2026_07_11_162254_change_users_two_factor_secret_to_text	1
67	2026_07_11_164542_assign_admin_role_to_company_admins	1
68	2026_07_12_130000_create_lookups_table	1
69	2026_07_12_130100_expand_job_positions_employment_type_for_work_type_lookup	1
70	2026_07_13_040000_extend_approval_engine_for_faz4b	1
71	2026_07_14_000001_add_system_fields_to_leave_types_table	1
72	2026_07_14_010001_add_must_change_password_to_users_table	1
73	2026_07_14_020001_add_branch_id_to_employees_table	1
74	2026_07_14_030001_create_positions_table	1
75	2026_07_14_113214_add_consent_and_converted_employee_to_job_applications_table	1
76	2026_07_15_010001_extend_custom_field_definitions_for_form_engine	2
77	2026_07_15_010002_create_form_definitions_table	2
78	2026_07_15_204151_create_document_expiry_alerts_table	3
79	2026_07_15_181924_add_escalation_days_and_approval_escalation_alerts	4
80	2026_07_15_185320_add_pdks_qr_fields_and_kiosk_tokens	5
81	2026_07_16_100000_add_attendance_calc_minutes_columns	6
82	2026_07_16_120000_add_process_type_and_offboarding_fields	7
83	2026_07_16_140000_create_salary_records_table	8
84	2026_07_16_141000_create_salary_bands_table	8
85	2026_07_16_142000_create_salary_review_tables	8
86	2026_07_16_143000_add_updated_by_to_salary_records	9
87	2026_07_16_220000_add_custom_fields_jsonb_to_leave_expense_asset	10
88	2026_07_16_230000_create_notification_templates_table	11
89	2026_07_16_240000_c5_announcements_target_branches_and_reads_user_id	12
90	2026_07_17_010000_c6_job_positions_form_definition_id	13
91	2026_07_29_100000_d1a_extend_saved_reports_for_report_engine	13
92	2026_07_29_140000_d1c_create_report_measures_table	13
93	2026_07_29_160000_d1d_create_dashboards_tables	14
94	2026_07_29_170000_d1e_sharing_access_privacy	15
95	2026_07_29_200000_d1f_report_schedules_and_perf	16
96	2026_07_29_210000_d1g_module_key_system_content	16
97	2026_07_29_220000_d2a_kvkk_foundation	16
98	2026_07_30_100000_d4a_create_setting_values_table	17
99	2026_07_30_120000_d2b_data_subject_requests	17
100	2026_07_30_140000_d2c_retention_destruction_breaches	17
101	2026_07_30_160000_d3_jsonb_gin_indexes	18
102	2026_07_31_150000_add_attendance_records_company_date_indexes	18
103	2026_08_04_120000_g1_organizations_and_company_context	18
104	2026_08_05_140000_add_full_name_to_employees_table	18
105	2026_08_05_153000_add_panel_access_to_roles_table	18
106	2026_08_05_220000_add_position_id_to_employees_table	18
107	2026_08_05_230000_convert_absolute_timestamps_to_timestamptz	18
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 107, true);


--
-- PostgreSQL database dump complete
--


