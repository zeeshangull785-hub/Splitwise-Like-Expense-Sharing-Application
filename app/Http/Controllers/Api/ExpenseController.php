<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Services\ExpenseService;
use App\Services\GroupService;
use App\Traits\ApiResponseTrait;
use App\Models\Expense;
use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExpenseController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected ExpenseService $expenseService,
        protected GroupService $groupService
    ) {}

    /**
     * List group expenses with pagination, filtering, and sorting.
     */
    public function index(string $groupId, Request $request): JsonResponse
    {
        $group = $this->groupService->getGroupForUser($groupId, $request->user());

        $query = Expense::where('group_id', (string)$group->_id);

        // Filter by payer
        if ($request->filled('payer')) {
            $query->where('paid_by', (string)$request->input('payer'));
        }

        // Filter by split_type
        if ($request->filled('split_type')) {
            $query->where('split_type', $request->input('split_type'));
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        // Filter by date_from
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        // Filter by date_to
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        // Search by description (Bonus)
        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->input('search') . '%');
        }

        // Sorting
        $sortField = $request->input('sort_by', 'created_at');
        $sortDirection = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (in_array($sortField, ['created_at', 'amount', 'description'], true)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = max(1, min(100, (int)$request->input('per_page', 20)));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Expenses retrieved successfully.',
            'data' => ExpenseResource::collection($paginated->items()),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'has_more' => $paginated->hasMorePages(),
            ],
        ]);
    }

    /**
     * Create an expense for a group.
     */
    public function store(string $groupId, CreateExpenseRequest $request): JsonResponse
    {
        $group = $this->groupService->getGroupForUser($groupId, $request->user());

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('receipts', 'public');
        }

        $expense = $this->expenseService->createExpense($group, $request->user(), $request->validated(), $receiptPath);

        return $this->successResponse(
            new ExpenseResource($expense),
            'Expense created successfully.',
            201
        );
    }

    /**
     * View expense details.
     */
    public function show(string $expenseId, Request $request): JsonResponse
    {
        $expense = Expense::find($expenseId);
        if (!$expense) {
            throw new ApiException('Expense not found.', 404);
        }

        // Authorization check: User must belong to the group
        $this->groupService->getGroupForUser((string)$expense->group_id, $request->user());

        return $this->successResponse(
            new ExpenseResource($expense),
            'Expense details retrieved successfully.'
        );
    }

    /**
     * Update an expense.
     */
    public function update(string $expenseId, UpdateExpenseRequest $request): JsonResponse
    {
        $expense = Expense::find($expenseId);
        if (!$expense) {
            throw new ApiException('Expense not found.', 404);
        }

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('receipts', 'public');
        }

        $updatedExpense = $this->expenseService->updateExpense($expense, $request->user(), $request->validated(), $receiptPath);

        return $this->successResponse(
            new ExpenseResource($updatedExpense),
            'Expense updated successfully.'
        );
    }

    /**
     * Delete an expense.
     */
    public function destroy(string $expenseId, Request $request): JsonResponse
    {
        $expense = Expense::find($expenseId);
        if (!$expense) {
            throw new ApiException('Expense not found.', 404);
        }

        $this->expenseService->deleteExpense($expense, $request->user());

        return $this->successResponse(null, 'Expense deleted successfully.');
    }
}
