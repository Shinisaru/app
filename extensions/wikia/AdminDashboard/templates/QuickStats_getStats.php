<section id="QuickStatsWidget" class="QuickStatsWidget">
	<h1><?= wfMsg('quickstats-header-label') ?></h1>
	<table class="WikiaDataTable">
		<thead class="AdminDashboardGeneralHeader">
			<tr>
				<th><?= wfMsg('quickstats-header-date') ?></th>
				<th><?= wfMsg('quickstats-header-views') ?></th>
				<th><?= wfMsg('quickstats-header-edits') ?></th>
				<th><?= wfMsg('quickstats-header-photos') ?></th>
				<th><?= wfMsg('quickstats-header-likes') ?></th>
			</tr>
		</thead>
		<tbody>
			<? 
				$totals = $stats['totals'];
				unset($stats['totals']);
  				foreach ($stats as $date => $row) { 
					$dateObject = new DateTime($date);
					$formattedDate = $dateObject->format(wfMsg('quickstats-date-format'));  ?>
				<tr>
					<td><?= $formattedDate ?></td>
					<td><?= $row['pageviews'] ?></td>
					<td><?= $row['edits'] ?></td>
					<td><?= $row['photos'] ?></td>
					<td><?= $row['likes'] ?></td>
				</tr>
			<? } ?>
		</tbody>
	</table>
</section>
