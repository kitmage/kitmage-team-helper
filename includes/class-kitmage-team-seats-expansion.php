<?php
/**
 * Team membership shortcodes for KitMage Team Explainer.
 *
 * @package KitMageTeamExplainer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers Team Seats Expansion shortcodes.
 */
final class KitMage_Team_Seats_Expansion {

	/** @var self|null */
	private static $instance = null;

	/**
	 * Return the singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Wire WordPress hooks.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
	}

	/**
	 * Register public shortcodes.
	 */
	public function register_shortcodes() {
		add_shortcode( 'teamx_name', array( $this, 'team_name_shortcode' ) );
		add_shortcode( 'teamx_restrict', array( $this, 'restrict_shortcode' ) );
		add_shortcode( 'teamx_status', array( $this, 'team_status_shortcode' ) );
	}

	/**
	 * Display the current user's team name.
	 *
	 * @return string
	 */
	public function team_name_shortcode() {
		$team = $this->get_current_user_team();

		if ( ! $team ) {
			return '';
		}

		return esc_html( $this->get_team_name( $team ) );
	}

	/**
	 * Display the current user's team membership status.
	 *
	 * @return string
	 */
	public function team_status_shortcode() {
		$team = $this->get_current_user_team();

		if ( ! $team ) {
			return '';
		}

		$statuses = $this->get_team_membership_statuses( $team );

		if ( empty( $statuses ) ) {
			return '';
		}

		return esc_html( $this->format_membership_status( reset( $statuses ) ) );
	}

	/**
	 * Conditionally display enclosed content based on team plan membership.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @param string|null         $content Enclosed shortcode content.
	 * @return string
	 */
	public function restrict_shortcode( $atts, $content = null ) {
		$atts = shortcode_atts(
			array(
				'plan'   => '',
				'status' => '',
				'mode'   => 'show',
			),
			(array) $atts,
			'teamx_restrict'
		);

		$matches = $this->current_user_matches_restriction( (string) $atts['plan'], (string) $atts['status'] );
		$mode    = strtolower( trim( (string) $atts['mode'] ) );
		$show    = 'hide' === $mode ? ! $matches : $matches;

		if ( ! $show ) {
			return '';
		}

		return do_shortcode( (string) $content );
	}

	/**
	 * Check whether the current user's team matches plan and status expressions.
	 *
	 * If no status expression is supplied, plan checks are limited to active
	 * memberships. If a status expression is supplied, plan checks are made
	 * against memberships matching that status expression.
	 *
	 * @param string $plan_expression Plan expression.
	 * @param string $status_expression Status expression.
	 * @return bool
	 */
	private function current_user_matches_restriction( $plan_expression, $status_expression ) {
		$team = $this->get_current_user_team();

		if ( ! $team ) {
			return false;
		}

		$memberships = $this->get_team_membership_data( $team );

		if ( '' !== trim( $status_expression ) ) {
			$memberships = array_filter(
				$memberships,
				function ( $membership ) use ( $status_expression ) {
					return $this->status_matches_expression( $membership['status'], $status_expression );
				}
			);

			if ( empty( $memberships ) ) {
				return false;
			}
		} else {
			$memberships = array_filter(
				$memberships,
				function ( $membership ) {
					return 'active' === $membership['status'];
				}
			);
		}

		if ( '' === trim( $plan_expression ) ) {
			return ! empty( $memberships );
		}

		return $this->plan_matches_expression(
			$this->get_plan_ids_from_membership_data( $memberships ),
			$plan_expression
		);
	}

	/**
	 * Check whether a set of plan IDs matches a plan expression.
	 *
	 * Commas are OR, plus signs are AND, and ! negates a token.
	 *
	 * @param int[]  $plan_ids Plan IDs.
	 * @param string $expression Plan expression.
	 * @return bool
	 */
	private function plan_matches_expression( $plan_ids, $expression ) {
		if ( '' === trim( $expression ) ) {
			return false;
		}

		foreach ( array_filter( array_map( 'trim', explode( ',', $expression ) ) ) as $or_group ) {
			$and_result = true;

			foreach ( array_filter( array_map( 'trim', explode( '+', $or_group ) ) ) as $token ) {
				$negated = 0 === strpos( $token, '!' );
				$plan_id = absint( $negated ? substr( $token, 1 ) : $token );

				if ( ! $plan_id ) {
					$and_result = false;
					break;
				}

				$has_plan   = in_array( $plan_id, $plan_ids, true );
				$and_result = $and_result && ( $negated ? ! $has_plan : $has_plan );
			}

			if ( $and_result ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether a membership status matches a status expression.
	 *
	 * @param string $status Status slug without the wcm- prefix.
	 * @param string $expression Status expression.
	 * @return bool
	 */
	private function status_matches_expression( $status, $expression ) {
		if ( '' === trim( $expression ) ) {
			return false;
		}

		foreach ( array_filter( array_map( 'trim', explode( ',', $expression ) ) ) as $or_group ) {
			$and_result = true;

			foreach ( array_filter( array_map( 'trim', explode( '+', $or_group ) ) ) as $token ) {
				$negated      = 0 === strpos( $token, '!' );
				$token_status = $this->normalize_membership_status( $negated ? substr( $token, 1 ) : $token );

				if ( '' === $token_status ) {
					$and_result = false;
					break;
				}

				$has_status = $status === $token_status;
				$and_result = $and_result && ( $negated ? ! $has_status : $has_status );
			}

			if ( $and_result ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the current user's single associated team, if available.
	 *
	 * @return object|false
	 */
	private function get_current_user_team() {
		$user_id = get_current_user_id();

		if ( ! $user_id || ! function_exists( 'wc_memberships_for_teams_get_teams' ) ) {
			return false;
		}

		$teams = wc_memberships_for_teams_get_teams(
			$user_id,
			array(
				'role'           => array( 'owner', 'manager', 'member' ),
				'posts_per_page' => 1,
			)
		);

		if ( is_array( $teams ) && ! empty( $teams ) ) {
			return reset( $teams );
		}

		return false;
	}

	/**
	 * Get a team display name.
	 *
	 * @param object $team Team object.
	 * @return string
	 */
	private function get_team_name( $team ) {
		if ( is_callable( array( $team, 'get_name' ) ) ) {
			return (string) $team->get_name();
		}

		if ( is_callable( array( $team, 'get_id' ) ) ) {
			return get_the_title( $team->get_id() );
		}

		return '';
	}

	/**
	 * Get membership data for the current user's membership tied to the team.
	 *
	 * @param object $team Team object.
	 * @return array<int,array{plan_id:int,status:string}>
	 */
	private function get_team_membership_data( $team ) {
		$memberships = array();

		foreach ( $this->get_current_user_team_user_memberships( $team ) as $user_membership ) {
			if ( ! is_callable( array( $user_membership, 'get_plan_id' ) ) ) {
				continue;
			}

			$memberships[] = array(
				'plan_id' => absint( $user_membership->get_plan_id() ),
				'status'  => $this->get_user_membership_status( $user_membership ),
			);
		}

		return array_values(
			array_filter(
				$memberships,
				function ( $membership ) {
					return ! empty( $membership['plan_id'] ) && ! empty( $membership['status'] );
				}
			)
		);
	}

	/**
	 * Get current user memberships that belong to the team.
	 *
	 * @param object $team Team object.
	 * @return array<int,object>
	 */
	private function get_current_user_team_user_memberships( $team ) {
		$user_id = get_current_user_id();

		if ( ! $user_id || ! function_exists( 'wc_memberships_get_user_memberships' ) ) {
			return array();
		}

		$user_memberships = wc_memberships_get_user_memberships(
			$user_id,
			array(
				'status' => 'any',
			)
		);

		if ( ! is_array( $user_memberships ) ) {
			return array();
		}

		$team_id      = $this->get_team_id( $team );
		$team_plan_id = is_callable( array( $team, 'get_plan_id' ) ) ? absint( $team->get_plan_id() ) : 0;
		$memberships  = array();

		foreach ( $user_memberships as $user_membership ) {
			if ( ! is_object( $user_membership ) || ! is_callable( array( $user_membership, 'get_id' ) ) ) {
				continue;
			}

			if ( $team_id && function_exists( 'wc_memberships_for_teams_get_user_membership_team' ) ) {
				$membership_team = wc_memberships_for_teams_get_user_membership_team( $user_membership->get_id() );

				if ( $membership_team && $team_id === $this->get_team_id( $membership_team ) ) {
					$memberships[] = $user_membership;
				}

				continue;
			}

			if ( $team_plan_id && is_callable( array( $user_membership, 'get_plan_id' ) ) && $team_plan_id === absint( $user_membership->get_plan_id() ) ) {
				$memberships[] = $user_membership;
			}
		}

		return $memberships;
	}

	/**
	 * Get a team ID from a team object.
	 *
	 * @param object $team Team object.
	 * @return int
	 */
	private function get_team_id( $team ) {
		if ( is_callable( array( $team, 'get_id' ) ) ) {
			return absint( $team->get_id() );
		}

		if ( isset( $team->ID ) ) {
			return absint( $team->ID );
		}

		return 0;
	}

	/**
	 * Get plan IDs from membership data.
	 *
	 * @param array<int,array{plan_id:int,status:string}> $memberships Membership data.
	 * @return int[]
	 */
	private function get_plan_ids_from_membership_data( $memberships ) {
		return array_values(
			array_unique(
				array_map(
					'absint',
					wp_list_pluck( $memberships, 'plan_id' )
				)
			)
		);
	}

	/**
	 * Get membership statuses for the team.
	 *
	 * @param object $team Team object.
	 * @return string[]
	 */
	private function get_team_membership_statuses( $team ) {
		$statuses = wp_list_pluck( $this->get_team_membership_data( $team ), 'status' );

		return array_values( array_unique( array_filter( $statuses ) ) );
	}

	/**
	 * Get normalized status from a user membership object.
	 *
	 * @param object $user_membership User membership object.
	 * @return string
	 */
	private function get_user_membership_status( $user_membership ) {
		if ( is_callable( array( $user_membership, 'get_status' ) ) ) {
			return $this->normalize_membership_status( $user_membership->get_status() );
		}

		if ( is_callable( array( $user_membership, 'get_id' ) ) ) {
			return $this->normalize_membership_status( get_post_status( $user_membership->get_id() ) );
		}

		return '';
	}

	/**
	 * Normalize membership statuses to slugs without the wcm- prefix.
	 *
	 * @param string $status Membership status.
	 * @return string
	 */
	private function normalize_membership_status( $status ) {
		$status = sanitize_key( (string) $status );

		if ( 0 === strpos( $status, 'wcm-' ) ) {
			$status = substr( $status, 4 );
		}

		return $status;
	}

	/**
	 * Format a membership status for display.
	 *
	 * @param string $status Membership status slug without the wcm- prefix.
	 * @return string
	 */
	private function format_membership_status( $status ) {
		return ucwords( str_replace( array( '-', '_' ), ' ', $this->normalize_membership_status( $status ) ) );
	}
}

KitMage_Team_Seats_Expansion::instance();
