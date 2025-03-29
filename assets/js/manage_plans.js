function editPlan(planId) {
    window.location.href = `edit_plan.php?plan_id=${planId}`;
}

function deletePlan(planId) {
    if (confirm('Are you sure you want to delete this membership plan?')) {
        window.location.href = `../actions/delete_plan.php?plan_id=${planId}`;
    }
}