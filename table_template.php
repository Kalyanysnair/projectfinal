<table class="table table-striped">
    <thead>
        <tr>
            <th>Request ID</th>
            <th>User</th>
            <th>Phone</th>
            <th>Pickup Location</th>
            <th>Amount (₹)</th>
            <th>Payment Status</th>
            <th>Date</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['request_id']; ?></td>
                <td><?= $row['username']; ?></td>
                <td><?= $row['phoneno']; ?></td>
                <td><?= $row['pickup_location']; ?></td>
                <td>₹<?= number_format($row['amount'], 2); ?></td>
                <td><span class="status-badge status-<?= strtolower($row['payment_status']); ?>">
                    <?= ucfirst($row['payment_status']); ?>
                </span></td>
                <td><?= date('d M Y, H:i A', strtotime($row['created_at'])); ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
