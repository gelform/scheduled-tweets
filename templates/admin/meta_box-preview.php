<blockquote id="<?php echo self::$post_type ?>-preview">
</blockquote>

<p style="font-style: italic; text-align: right;">
	Char count: <span id="<?php echo self::$post_type ?>-preview-count"></span>
</p>

<style>
	#<?php echo self::$post_type ?>-preview {
		background: #DEF;
		border-radius: 10px;
		padding: 10px 10px 20px;
		position: relative;
	}

	#<?php echo self::$post_type ?>-preview p {
		margin: 0 0 10px;
	}

	#<?php echo self::$post_type ?>-preview:before {
		width: 0;
		height: 0;
		border-style: solid;
		border-width: 30px 30px 0 0;
		border-color: #DEF transparent transparent transparent;

		display: block;
		content: '';
		left: 10px;
		position: absolute;
		top: 100%;

	}
</style>