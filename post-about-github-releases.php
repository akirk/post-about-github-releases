<?php
/**
 * Plugin Name: Post about Github Releases
 * Description: Create blog posts for new releases from Github.
 * Version: 1.0
 * Text Domain: post-about-github-releases
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Post_About_Github_Releases {
	private $error = false;
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'github_releases_menu' ) );
	}

	public function github_releases_menu() {
		$hook = add_posts_page( 'Github Releases', 'Github Releases', 'manage_options', 'github-releases', array( $this, 'github_releases_page' ) );
		add_action( 'load-' . $hook, array( $this, 'process_github_releases_page' ) );
	}

	public function process_github_releases_page() {
		if ( isset( $_POST['create_summary_post'] ) ) {
			if ( ! isset( $_POST['create_summary_post_nonce'] ) || ! wp_verify_nonce( $_POST['create_summary_post_nonce'], 'create_summary_post' ) ) {
				wp_die( 'Security check' );
			}

			$post_date = sanitize_text_field( $_POST['post_date'] );
			$post_title = sanitize_text_field( $_POST['post_title'] );
			$post_content = wp_kses_post( $_POST['post_content'] );
			$github_release_ids = isset( $_POST['github_release_ids'] ) ? array_filter( explode( ',', $_POST['github_release_ids'] ) ) : array();

			$post_id = wp_insert_post(
				array(
					'post_title'   => $post_title,
					'post_content' => $post_content,
					'post_status'  => 'draft',
					'post_author'  => 1,
					'post_type'    => 'post',
					'post_date'    => $post_date,
				)
			);

			if ( ! is_wp_error( $post_id ) ) {
				foreach ( $github_release_ids as $github_release_id ) {
					add_post_meta( $post_id, 'github_release_id', $github_release_id );
				}

				wp_safe_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
				exit;
			}

			$this->error = '<div class="error"><p>';

			$this->error .= esc_html__( 'Error creating the post.', 'post-about-github-releases' );
			$this->error .= ' ';
			$this->error .= esc_html(
				sprintf(
					// translators: %s is an error message.
					__( 'Error: %s', 'post-about-github-releases' ),
					$post_id->get_error_message()
				)
			);
			$this->error .= '</p></div>';
		}
	}
	public function github_releases_page() {

		$repo = get_option( 'pagh_github_repo' );
		$post_content = get_option( 'pagh_initial_text' );

		if ( isset( $_POST['submit_github_repo'] ) ) {
			if ( ! isset( $_POST['submit_github_repo_nonce'] ) || ! wp_verify_nonce( $_POST['submit_github_repo_nonce'], 'submit_github_repo' ) ) {
				wp_die( 'Security check' );
			}

			update_option( 'pagh_github_repo', sanitize_text_field( $_POST['pagh_github_repo'] ) );
			$repo = get_option( 'pagh_github_repo' );

			update_option( 'pagh_initial_text', wp_kses_post( $_POST['pagh_initial_text'] ) );
			$post_content = get_option( 'pagh_initial_text' );
		}

		?>
	<div class="wrap">
		<h2><?php esc_html_e( 'Post About Github Releases', 'post-about-github-releases' ); ?></h2>
		<p><?php esc_html_e( 'Create summary posts from selected releases from your Github Repo.', 'post-about-github-releases' ); ?></p>
			<?php

			if ( $this->error ) {
				echo wp_kses_post( $this->error );
			}

			if ( $repo ) {
				?>
		<details style="border: 1px solid #ccc; padding: 1em; margin-bottom: 1em">
			<summary style="cursor: pointer;">Change Repo settings</summary>
				<?php
			} else {
				?>
			<div class="error"><p><?php esc_html_e( 'Please configure a GitHub repository.', 'post-about-github-releases' ); ?></p></div>
				<?php
			}
			?>

		<form method="post">
			<?php wp_nonce_field( 'submit_github_repo', 'submit_github_repo_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="pagh_github_repo"><?php esc_html_e( 'Address of your Github Repo', 'post-about-github-releases' ); ?></label></th>
					<td>https://github.com/<input type="text" name="pagh_github_repo" value="<?php echo esc_attr( $repo ); ?>" placeholder="org/name" pattern="^[a-zA-Z0-9_\-]+/[a-zA-Z0-9_\-]$" class="regular-text" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="pagh_initial_text"><?php esc_html_e( 'Initial Text', 'post-about-github-releases' ); ?></label></th>
					<td><textarea
						name="pagh_initial_text"
						class="large-text"
						placeholder="<?php esc_attr_e( 'A new version of the software has been released. This version includes the following changes:', 'post-about-github-releases' ); ?>"
						><?php echo esc_textarea( $post_content ); ?></textarea></td>
				</tr>
			</table>


			<button class="button button-primary" type="submit" name="submit_github_repo"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Save Changes' ); ?></button>


		</form>
			<?php if ( $repo ) { ?>
		</details>
		<?php } ?>
			<?php

			if ( ! $repo ) {
				?>
		</div>
				<?php
				exit;
			}

			$transient_key = 'cached_github_releases' . $repo;
			$github_releases = get_transient( $transient_key );

			if ( ! $github_releases ) {
				$url = 'https://api.github.com/repos/' . $repo . '/releases';
				$response = wp_safe_remote_get( $url );
				if ( is_wp_error( $response ) ) {
					echo '<div class="error"><p>';

					echo wp_kses(
						sprintf(
							// translators: %s is a URL.
							__( 'Error <a href="%s">fetching releases from Github</a>. Please try again later.', 'post-about-github-releases' ),
							$url
						),
						array( 'a' => array( 'href' => array() ) )
					);
					echo ' ';
					echo esc_html(
						sprintf(
							// translators: %s is an error message.
							__( 'Error: %s', 'post-about-github-releases' ),
							$response->get_error_message()
						)
					);
					echo '</p></div>';
					exit;
				}

				$github_releases = json_decode( wp_remote_retrieve_body( $response ) );

				$parsedown = new Parsedown();
				foreach ( $github_releases as $k => $release ) {
					$github_releases[ $k ]->body = $parsedown->text( $release->body );
				}

				set_transient( $transient_key, $github_releases, 3600 );
			}
			$post_date = '';
			$post_title = '';
			$github_release_ids = '';

			?>
		<form id="summary-form" method="post">
			<?php wp_nonce_field( 'create_summary_post', 'create_summary_post_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="post_title"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Post Title' ); ?></label></th>
					<td><input type="text" name="post_title" value="<?php echo esc_attr( $post_title ); ?>" class="regular-text" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="post_date"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Post Date' ); ?></label></th>
					<td><input type="datetime-local" name="post_date" value="<?php echo esc_attr( $post_date ); ?>" required /></td>
				</tr>
			</table>
			<?php
			wp_editor(
				$post_content,
				'post_content',
				array(
					'textarea_name' => 'post_content',
					'textarea_rows' => 10,
					'media_buttons' => false,

				)
			);
			?>
			<div id="selected-releases"></div>
			<input type="hidden" name="github_release_ids">
			<button class="button button-primary" type="submit" name="create_summary_post"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Save Draft' ); ?></button>
		</form>

			<?php
			foreach ( $github_releases as $release ) :
				$existing_post = get_posts(
					array(
						'meta_key'     => 'github_release_id',
						'meta_value'   => $release->id,
						'meta_compare' => 'IN',
						'post_type'    => 'post',
						'post_status'  => array( 'draft', 'publish' ),
					)
				);

				if ( ! empty( $existing_post ) ) {
					continue;
				}
				?>
			<div>
				<label><input type="checkbox" class="release-checkbox" value="<?php echo esc_attr( $release->id ); ?>" id="release-<?php echo esc_attr( $release->id ); ?>">
				<h3 style="display: inline-block"><?php echo esc_html( $release->name ); ?></h3>
				on <time datetime="<?php echo esc_attr( $release->created_at ); ?>"><?php echo esc_html( date_i18n( 'l, F j, Y H:i', strtotime( $release->created_at ), true ) ); ?></time>
				</label>
				<div><?php echo wp_kses_post( $release->body ); ?></div>
			</div>
			<?php endforeach; ?>
	</ul>

</div>

<script>
	document.addEventListener("DOMContentLoaded", function() {
		const checkboxes = document.querySelectorAll('.release-checkbox');
		const selectedReleases = document.getElementById('selected-releases');
		const githubReleaseIdsInput = document.querySelector('input[name="github_release_ids"]');

		checkboxes.forEach(checkbox => {
			checkbox.addEventListener('change', function() {
				const post_title = document.querySelector('input[name="post_title"]');
				const version = checkbox.closest('div').querySelector('h3').textContent;
				const post_date = document.querySelector('input[name="post_date"]');

				if ( ! post_title.value ) {
					post_title.value = version;
				}
				jQuery('#post_content_ifr').contents().find('body').append('<h2>'+version+'</h2>'+checkbox.closest('div').querySelector('div').innerHTML);
				if ( ! post_date.value ) {
					post_date.value = checkbox.closest('div').querySelector('time').dateTime.replace('T', ' ').slice(0, 16)
				}

				if (checkbox.checked) {
					const releaseItem = document.createElement('span');
					releaseItem.textContent = version;
					selectedReleases.appendChild(releaseItem);

					const githubReleaseIds = githubReleaseIdsInput.value.split(',');
					githubReleaseIds.push(checkbox.value);
					githubReleaseIdsInput.value = githubReleaseIds.join(',');
				} else {
					const githubReleaseIds = githubReleaseIdsInput.value.split(',');
					const index = githubReleaseIds.indexOf(checkbox.value);
					if (index !== -1) {
						githubReleaseIds.splice(index, 1);
						githubReleaseIdsInput.value = githubReleaseIds.join(',');

						selectedReleases.querySelectorAll('span').forEach(item => {
							if (item.textContent === version) {
								item.remove();
							}
						});
					}
				}
			});
		});
	});
</script>
			<?php
	}
}
new Post_About_Github_Releases();
