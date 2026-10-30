<?php
// Script to display all tables and their contents from the SQLite database.
// Usage: open in browser: http://localhost/UAIC_projects/utils/show_DB.php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';

// Get list of user tables
$tables = DB::getData("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name", []);

?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>DB Browser</title>
	<link rel="stylesheet" href="../css/bootstrap-4.1.0.min.css">
	<style>body{padding:20px;} table{font-size:14px;}</style>
</head>
<body>
<div class="container">
	<h1>Database tables</h1>
	<?php if (count($tables) === 0): ?>
		<div class="alert alert-warning">No tables found in the database.</div>
	<?php else: ?>
		<?php foreach ($tables as $t):
			$table = isset($t['name']) ? $t['name'] : (isset($t[0]) ? $t[0] : null);
			if (empty($table)) continue;
			$safe = str_replace('"', '""', $table);
			$rows = DB::getData("SELECT * FROM \"$safe\"", []);
		?>
		<div class="card mb-4">
			<div class="card-header"><strong><?php echo htmlspecialchars($table, ENT_QUOTES, 'UTF-8'); ?></strong> (<?php echo count($rows); ?> rows)</div>
			<div class="card-body p-0">
				<?php if (count($rows) === 0): ?>
					<div class="p-3">Empty table</div>
				<?php else: ?>
					<div class="table-responsive">
						<table class="table table-sm table-bordered mb-0">
							<thead class="thead-light">
								<tr>
									<?php foreach (array_keys($rows[0]) as $col): ?>
										<th><?php echo htmlspecialchars($col, ENT_QUOTES, 'UTF-8'); ?></th>
									<?php endforeach; ?>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($rows as $r): ?>
									<tr>
										<?php foreach ($r as $v): ?>
											<td><?php echo htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?></td>
										<?php endforeach; ?>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
</body>
</html>

