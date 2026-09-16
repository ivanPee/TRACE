import React, { useEffect, useMemo, useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import FontAwesome5 from 'react-native-vector-icons/FontAwesome5';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import FormInput from '../../components/FormInput';
import HeaderBlock from '../../components/HeaderBlock';
import InfoRow from '../../components/InfoRow';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';
import { colors } from '../../theme/colors';

const BASE_DAILY_FARE = 40;
const PER_KM_RATE = 3;
const DISCOUNT_RATE = 0.4;
const tripTypeOptions = [
  { value: 'one_way', label: 'One way', icon: 'arrow-right' },
  { value: 'round_trip', label: 'Round trip', icon: 'exchange-alt' },
];

const pad = (value) => String(value).padStart(2, '0');

const toDateValue = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

const currentBillingDate = () => toDateValue(new Date());

const money = (value) => `PHP ${Number(value || 0).toFixed(2)}`;

const normalizeCoordinate = (value) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : null;
};

const destinationKey = (student) => {
  const latitude = normalizeCoordinate(student.dropoffLatitude);
  const longitude = normalizeCoordinate(student.dropoffLongitude);

  if (latitude !== null && longitude !== null) {
    return `${latitude.toFixed(5)},${longitude.toFixed(5)}`;
  }

  return String(student.dropoffAddress || '').trim().toLowerCase();
};

const distanceKm = (student) => {
  const fromLat = normalizeCoordinate(student.pickupLatitude);
  const fromLng = normalizeCoordinate(student.pickupLongitude);
  const toLat = normalizeCoordinate(student.dropoffLatitude);
  const toLng = normalizeCoordinate(student.dropoffLongitude);

  if ([fromLat, fromLng, toLat, toLng].some((value) => value === null)) {
    return 0;
  }

  const earthRadiusKm = 6371;
  const latDelta = ((toLat - fromLat) * Math.PI) / 180;
  const lngDelta = ((toLng - fromLng) * Math.PI) / 180;
  const a =
    Math.sin(latDelta / 2) ** 2 +
    Math.cos((fromLat * Math.PI) / 180) * Math.cos((toLat * Math.PI) / 180) * Math.sin(lngDelta / 2) ** 2;

  return earthRadiusKm * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
};

const hasCompleteRoute = (student) => {
  const pickupAddress = String(student.pickupAddress || '').trim();
  const dropoffAddress = String(student.dropoffAddress || '').trim();
  const pickupLatitude = normalizeCoordinate(student.pickupLatitude);
  const pickupLongitude = normalizeCoordinate(student.pickupLongitude);
  const dropoffLatitude = normalizeCoordinate(student.dropoffLatitude);
  const dropoffLongitude = normalizeCoordinate(student.dropoffLongitude);

  return Boolean(pickupAddress && dropoffAddress && pickupLatitude !== null && pickupLongitude !== null && dropoffLatitude !== null && dropoffLongitude !== null);
};

const daysInBillingMonth = () => 30;

const monthLabel = (value) => {
  const [year, month, day = 1] = String(value || currentBillingDate()).split('-').map(Number);
  const date = new Date(year || new Date().getFullYear(), (month || 1) - 1, day || 1);

  return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
};

const shiftDate = (value, days) => {
  const [year, month, day] = String(value || currentBillingDate()).split('-').map(Number);
  const date = new Date(year || new Date().getFullYear(), (month || 1) - 1, day || 1);
  date.setDate(date.getDate() + days);

  return toDateValue(date);
};

const toMinutes = (time) => {
  const [hour, minute] = String(time || '00:00').split(':').map(Number);

  return (Number.isFinite(hour) ? hour : 0) * 60 + (Number.isFinite(minute) ? minute : 0);
};

const fromMinutes = (minutes) => {
  const clamped = Math.max(0, Math.min(23 * 60 + 45, minutes));

  return `${pad(Math.floor(clamped / 60))}:${pad(clamped % 60)}`;
};

const shiftTime = (time, minutes) => fromMinutes(toMinutes(time) + minutes);

function TimestampButton({ icon, label, onPress }) {
  return (
    <Pressable style={({ pressed }) => [styles.timestampButton, pressed && styles.pressed]} onPress={onPress}>
      {icon ? <FontAwesome5 name={icon} size={12} solid color={colors.ink} /> : null}
      <Text style={styles.timestampButtonText}>{label}</Text>
    </Pressable>
  );
}

function DateField({ label, value, onChange }) {
  return (
    <View style={styles.timestampField}>
      <Text style={styles.fieldLabel}>{label}</Text>
      <View style={styles.timestampControl}>
        <TimestampButton icon="chevron-left" label="Day" onPress={() => onChange(shiftDate(value, -1))} />
        <View style={styles.timestampValue}>
          <Text style={styles.timestampMain}>{monthLabel(value)}</Text>
          <Text style={styles.timestampMeta}>{value}</Text>
        </View>
        <TimestampButton label="Day" icon="chevron-right" onPress={() => onChange(shiftDate(value, 1))} />
      </View>
      <View style={styles.quickRow}>
        <TimestampButton icon="calendar-check" label="Today" onPress={() => onChange(currentBillingDate())} />
        <TimestampButton icon="calendar-plus" label="Next Week" onPress={() => onChange(shiftDate(currentBillingDate(), 7))} />
        <TimestampButton icon="calendar-alt" label="Next Month" onPress={() => onChange(shiftDate(currentBillingDate(), 30))} />
      </View>
    </View>
  );
}

function TimeField({ label, value, onChange, presets }) {
  return (
    <View style={styles.timestampField}>
      <Text style={styles.fieldLabel}>{label}</Text>
      <View style={styles.timestampControl}>
        <TimestampButton icon="minus" label="15m" onPress={() => onChange(shiftTime(value, -15))} />
        <View style={styles.timestampValue}>
          <Text style={styles.timestampMain}>{value}</Text>
          <Text style={styles.timestampMeta}>Route window</Text>
        </View>
        <TimestampButton icon="plus" label="15m" onPress={() => onChange(shiftTime(value, 15))} />
      </View>
      <View style={styles.quickRow}>
        {presets.map((preset) => (
          <TimestampButton key={preset} label={preset} onPress={() => onChange(preset)} />
        ))}
      </View>
    </View>
  );
}

export default function BookRideScreen({ navigation }) {
  const { students, payMonthlyPlan, refreshDashboard } = useAppContext();
  const [refreshing, setRefreshing] = useState(false);
  const [selectedStudentIds, setSelectedStudentIds] = useState([]);
  const [billingDate, setBillingDate] = useState(currentBillingDate());
  const [monthlyTripType, setMonthlyTripType] = useState('one_way');
  const [pickupTime, setPickupTime] = useState('07:00');
  const [dropoffTime, setDropoffTime] = useState('07:45');
  const [referenceNumber, setReferenceNumber] = useState('');

  useEffect(() => {
    if (!selectedStudentIds.length && students[0]) {
      setSelectedStudentIds([students[0].id]);
    }
  }, [selectedStudentIds.length, students]);

  const selectedStudents = useMemo(
    () => students.filter((student) => selectedStudentIds.some((id) => String(id) === String(student.id))),
    [selectedStudentIds, students]
  );
  const routeGroups = useMemo(() => {
    const groups = new Map();

    selectedStudents.forEach((student) => {
      const key = destinationKey(student);
      if (!groups.has(key)) {
        groups.set(key, {
          key,
          dropoffAddress: student.dropoffAddress,
          children: [],
        });
      }

      groups.get(key).children.push(student);
    });

    return Array.from(groups.values()).filter((group) => group.key);
  }, [selectedStudents]);
  const selectedRoutesComplete = selectedStudents.length > 0 && selectedStudents.every(hasCompleteRoute);
  const serviceDays = daysInBillingMonth(billingDate);
  const childBreakdown = selectedStudents.map((student, index) => {
    const kilometers = distanceKm(student);
    const grossDailyAmount = BASE_DAILY_FARE + kilometers * PER_KM_RATE;
    const discountAmount = selectedStudents.length > 1 && index > 0 ? grossDailyAmount * DISCOUNT_RATE : 0;

    return {
      student,
      kilometers,
      grossDailyAmount,
      discountAmount,
      netDailyAmount: grossDailyAmount - discountAmount,
    };
  });
  const grossDailyTotal = childBreakdown.reduce((sum, item) => sum + item.grossDailyAmount, 0);
  const discountAmount = childBreakdown.reduce((sum, item) => sum + item.discountAmount, 0);
  const tripMultiplier = monthlyTripType === 'round_trip' ? 2 : 1;
  const dailyTotal = (grossDailyTotal - discountAmount) * tripMultiplier;
  const refundPerCancelledDay = dailyTotal * 0.5;
  const monthlyTotal = dailyTotal * serviceDays;

  const toggleStudent = (studentId) => {
    setSelectedStudentIds((current) => {
      if (current.some((id) => String(id) === String(studentId))) {
        return current.filter((id) => String(id) !== String(studentId));
      }

      return [...current, studentId];
    });
  };

  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await refreshDashboard();
    } finally {
      setRefreshing(false);
    }
  };

  const handleSubmit = async () => {
    if (!selectedStudentIds.length) {
      Alert.alert('Monthly plan', 'Select at least one child.');
      return;
    }

    if (!selectedRoutesComplete) {
      Alert.alert('Monthly plan', 'Complete each selected child pickup and drop-off pin before payment.');
      return;
    }

    if (toMinutes(dropoffTime) <= toMinutes(pickupTime)) {
      Alert.alert('Monthly plan', 'Set a drop-off time later than the pickup time.');
      return;
    }

    try {
      await payMonthlyPlan({
        studentIds: selectedStudentIds,
        billingMonth: billingDate,
        monthlyTripType,
        pickupTime,
        dropoffTime,
        paymentMethod: 'cash',
        referenceNumber,
      });
      navigation.goBack();
    } catch (error) {
      Alert.alert('Monthly plan', error.message || 'Unable to create the monthly plan.');
    }
  };

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="plans" />} refreshing={refreshing} onRefresh={handleRefresh}>
      <HeaderBlock
        eyebrow="Monthly Plan"
        title="Create a monthly child transport plan."
        subtitle="Parents pay the route total once, then the app opens daily transport routes for approved drivers."
      />

      {!students.length ? (
        <SectionCard title="No child profiles yet" icon="child">
          <Text style={styles.bodyText}>Register a child account before creating a monthly plan.</Text>
          <AppButton icon="user-plus" label="Add Child Account" onPress={() => navigation.navigate('AddStudent')} />
        </SectionCard>
      ) : (
        <>
          <SectionCard title="Select child or children" subtitle="Each selected child can keep their own pickup and drop-off route." icon="users">
            {students.map((student) => {
              const selected = selectedStudentIds.some((id) => String(id) === String(student.id));

              return (
                <Pressable key={student.id} style={[styles.childRow, selected && styles.childRowSelected]} onPress={() => toggleStudent(student.id)}>
                  <View style={[styles.check, selected && styles.checkSelected]}>
                    {selected ? <FontAwesome5 name="check" size={11} solid color={colors.white} /> : null}
                  </View>
                  <View style={styles.childText}>
                    <Text style={styles.childName}>{student.name}</Text>
                    <Text style={styles.childMeta}>{student.schoolName || student.dropoffAddress}</Text>
                  </View>
                </Pressable>
              );
            })}
          </SectionCard>

          <SectionCard title="Plan setup" icon="calendar-plus">
            <DateField label="Billing Date" value={billingDate} onChange={setBillingDate} />
            <Text style={styles.fieldLabel}>Trip Type</Text>
            <View style={styles.tripTypeRow}>
              {tripTypeOptions.map((option) => {
                const selected = monthlyTripType === option.value;

                return (
                  <Pressable key={option.value} style={[styles.tripTypeOption, selected && styles.tripTypeOptionSelected]} onPress={() => setMonthlyTripType(option.value)}>
                    <FontAwesome5 name={option.icon} size={13} solid color={selected ? colors.white : colors.ink} />
                    <Text style={[styles.tripTypeText, selected && styles.tripTypeTextSelected]}>{option.label}</Text>
                  </Pressable>
                );
              })}
            </View>
            <TimeField label="Pickup Time" value={pickupTime} onChange={setPickupTime} presets={['06:30', '07:00', '07:30']} />
            <TimeField label={monthlyTripType === 'round_trip' ? 'Return Pickup Time' : 'Drop-off Time'} value={dropoffTime} onChange={setDropoffTime} presets={monthlyTripType === 'round_trip' ? ['15:30', '16:00', '16:30'] : ['07:30', '07:45', '08:00']} />
            <FormInput label="Payment Reference" value={referenceNumber} onChangeText={setReferenceNumber} placeholder="Optional receipt or note" />
            {routeGroups.map((route, index) => (
              <InfoRow key={route.key || index} icon="flag-checkered" label={`Route ${index + 1}`} value={`${route.children.length} child${route.children.length === 1 ? '' : 'ren'} / ${route.dropoffAddress || '-'}`} />
            ))}
            {selectedStudents.length > 0 && !selectedRoutesComplete ? (
              <Text style={styles.warningText}>Complete pickup and drop-off pins for every selected child before payment.</Text>
            ) : null}
          </SectionCard>

          <SectionCard title="Fare summary" subtitle="30 weekday service days. Base fare PHP 40 per trip, plus PHP 3 per kilometer." icon="receipt">
            <Pill label={selectedStudents.length > 1 ? '40% discount on extra children' : 'First child at full route fare'} tone="success" />
            {childBreakdown.map(({ student, kilometers, grossDailyAmount, discountAmount: childDiscount }) => (
              <InfoRow key={student.id} icon="child" label={student.name} value={`${kilometers.toFixed(1)} km / ${money(grossDailyAmount - childDiscount)} daily${childDiscount > 0 ? ` after ${money(childDiscount)} off` : ''}`} />
            ))}
            <View style={styles.divider} />
            <InfoRow icon="calendar-day" label="Service days" value={`${serviceDays}`} />
            <InfoRow icon="route" label="Trip type" value={monthlyTripType === 'round_trip' ? 'Round trip' : 'One way'} />
            <InfoRow icon="clock" label="Daily trip window" value={monthlyTripType === 'round_trip' ? `${pickupTime} to school, ${dropoffTime} return home` : `${pickupTime} pickup, ${dropoffTime} drop-off`} />
            <InfoRow icon="coins" label="Gross daily route" value={money(grossDailyTotal * tripMultiplier)} />
            <InfoRow icon="percentage" label="Discount" value={money(discountAmount * tripMultiplier)} />
            <InfoRow icon="wallet" label="Parent pays" value={money(monthlyTotal)} />
            <InfoRow icon="undo" label="No-class refund" value={`${money(refundPerCancelledDay)} per cancelled day`} />
            <AppButton icon="credit-card" label="Pay and Create Monthly Plan" onPress={handleSubmit} />
          </SectionCard>
        </>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  bodyText: {
    color: colors.deep,
    lineHeight: 20,
    marginBottom: 12,
  },
  childRow: {
    alignItems: 'center',
    borderColor: colors.line,
    borderRadius: 14,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 12,
    marginBottom: 10,
    padding: 12,
  },
  childRowSelected: {
    backgroundColor: colors.accentSoft,
    borderColor: colors.accent,
  },
  check: {
    alignItems: 'center',
    borderColor: colors.line,
    borderRadius: 9,
    borderWidth: 1,
    height: 22,
    justifyContent: 'center',
    width: 22,
  },
  checkSelected: {
    backgroundColor: colors.accent,
    borderColor: colors.accent,
  },
  childText: {
    flex: 1,
  },
  childName: {
    color: colors.ink,
    fontSize: 15,
    fontWeight: '800',
  },
  childMeta: {
    color: colors.slate,
    fontSize: 13,
    lineHeight: 18,
    marginTop: 2,
  },
  warningText: {
    color: colors.danger,
    fontWeight: '700',
    lineHeight: 20,
  },
  divider: {
    backgroundColor: colors.line,
    height: 1,
    marginBottom: 10,
    marginTop: 8,
  },
  fieldLabel: {
    color: colors.ink,
    fontSize: 14,
    fontWeight: '700',
    marginBottom: 6,
  },
  pressed: {
    opacity: 0.78,
  },
  timestampField: {
    marginBottom: 14,
  },
  timestampControl: {
    alignItems: 'center',
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderRadius: 14,
    borderWidth: 1,
    flexDirection: 'row',
    minHeight: 64,
    padding: 8,
  },
  timestampButton: {
    alignItems: 'center',
    backgroundColor: colors.accentSoft,
    borderRadius: 8,
    flexDirection: 'row',
    gap: 6,
    justifyContent: 'center',
    minHeight: 38,
    minWidth: 66,
    paddingHorizontal: 10,
  },
  timestampButtonText: {
    color: colors.ink,
    fontSize: 12,
    fontWeight: '800',
  },
  timestampValue: {
    alignItems: 'center',
    flex: 1,
    justifyContent: 'center',
    paddingHorizontal: 8,
  },
  timestampMain: {
    color: colors.ink,
    fontSize: 17,
    fontWeight: '900',
    textAlign: 'center',
  },
  timestampMeta: {
    color: colors.deep,
    fontSize: 11,
    marginTop: 3,
    textAlign: 'center',
  },
  quickRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 8,
  },
  tripTypeRow: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: 14,
  },
  tripTypeOption: {
    alignItems: 'center',
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderRadius: 8,
    borderWidth: 1,
    flex: 1,
    flexDirection: 'row',
    gap: 8,
    justifyContent: 'center',
    minHeight: 46,
    paddingHorizontal: 10,
  },
  tripTypeOptionSelected: {
    backgroundColor: colors.ink,
    borderColor: colors.ink,
  },
  tripTypeText: {
    color: colors.ink,
    fontSize: 13,
    fontWeight: '800',
  },
  tripTypeTextSelected: {
    color: colors.white,
  },
});
