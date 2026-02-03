# Migration to Points-Based System

## Overview
The controle system has been migrated from a complex percentage-weighted scoring system to a simpler direct points system.

## What Changed

### Before (Percentage-Weighted System)
- Each phase had:
  - `percentage`: Weight of the phase in final grade (e.g., 25%)
  - `max_points`: Maximum score for the phase (e.g., 20 points)
- Final grade calculation:
  ```
  final_note = Σ ((raw_note / max_points) × percentage × (total_points / 100))
  ```
- Validation: Percentages must sum to 100%

### After (Direct Points System)
- Each phase now has:
  - `points`: Exact point value the phase is worth
- Final grade calculation:
  ```
  final_note = Σ (raw_note)
  ```
- Validation: Phase points must sum to template's total_points

## Database Changes

### Schema Migration (006_convert_to_points_system.sql)
```sql
-- Adds 'points' column
-- Converts existing data: points = (percentage / 100) * template.total_points
-- Drops 'percentage' and 'max_points' columns
```

### How to Apply Migration

1. **Backup your database first!**
   ```bash
   mysqldump -u root -p no9ati_db > backup_before_migration.sql
   ```

2. **Run the migration:**
   ```bash
   mysql -u root -p no9ati_db < migrations/006_convert_to_points_system.sql
   ```

3. **Verify the migration:**
   ```sql
   SELECT t.title as template, t.total_points, 
          p.title as phase, p.points
   FROM controle_templates t
   LEFT JOIN controle_template_phases p ON t.id = p.template_id
   ORDER BY t.id, p.id;
   ```

   Check that:
   - All phases have reasonable point values
   - Sum of phase points equals template total_points for each template

## Code Changes

### Updated Files

#### Core Classes
- `classes/Controle.php`:
  - `addPhase()`: Now takes `$points` instead of `$percentage, $max_points`
  - `updatePhase()`: Simplified parameters to use `$points`
  - `validatePhasePoints()`: Renamed from `validatePhasePercentages()`, checks sum
  - `computeStudentResult()`: Simplified to direct summation
  - `saveIndividualNote()` / `saveTeamNote()`: Validation uses `$phase['points']`

#### Views
- `views/controle_template_edit.php`:
  - Phase creation form: Single "Points" field
  - Validation: Checks points sum = template total
  - Preview: Shows direct point contributions
  
- `views/controle_fill_notes.php`:
  - Phase headers show "X pts" instead of "X% • Max: Y"
  - Input validation uses phase points
  
- `views/controle_instance_results.php`:
  - Column headers show "X pts" instead of "X%"
  - Phase breakdown shows points
  
- `views/controles.php`:
  - Template card shows "Total pts: X/Y" instead of "Total %"

## Benefits

1. **Simpler to Understand**: "This phase is worth 5 points" vs "This phase is 25% weighted with max 20 points"
2. **Easier Mental Math**: Teachers can directly see point values
3. **No Confusion**: Single point value instead of dual percentage/max_points concept
4. **Flexible**: Can distribute points however needed (3pts, 5pts, 12pts, etc.)

## Example

### Creating a Template (20 points total)
```
Old way:
- Phase 1: 25% weight, 20 max → contributes 5 pts to final
- Phase 2: 50% weight, 30 max → contributes 10 pts to final
- Phase 3: 25% weight, 10 max → contributes 5 pts to final

New way:
- Phase 1: 5 points
- Phase 2: 10 points
- Phase 3: 5 points
Total: 20 points ✓
```

### Grading
```
Old calculation:
Student gets 15/20 on Phase 1 (25% weight):
contribution = (15/20) × 25 × (20/100) = 3.75 pts

New calculation:
Student gets 3/5 on Phase 1:
contribution = 3 pts (simple!)
```

## Rollback (If Needed)

If you need to rollback:

1. Restore database from backup:
   ```bash
   mysql -u root -p no9ati_db < backup_before_migration.sql
   ```

2. Revert code changes using git:
   ```bash
   git revert <commit-hash>
   ```

## Testing Checklist

After migration, test:
- [ ] Create new template with phases (points should sum to total)
- [ ] Edit existing template phases
- [ ] Create controle instance from template
- [ ] Grade students in all phases
- [ ] View results page (all calculations correct)
- [ ] Generate certificates (eligibility correct)
- [ ] Duplicate template (phases copy correctly)

## Support

If you encounter issues:
1. Check migration verification query results
2. Ensure all phase points sum to template totals
3. Check browser console for JavaScript errors
4. Review PHP error logs for backend issues
