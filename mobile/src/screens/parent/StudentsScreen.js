import React, { useState } from 'react';
import AddressPinPicker from '../../components/AddressPinPicker';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import FormInput from '../../components/FormInput';
import HeaderBlock from '../../components/HeaderBlock';
import InfoRow from '../../components/InfoRow';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

export default function StudentsScreen({ navigation }) {
  const { currentRole, students, updateStudent, refreshDashboard } = useAppContext();
  const [editingId, setEditingId] = useState(null);
  const [form, setForm] = useState({});
  const [refreshing, setRefreshing] = useState(false);
  const isParent = currentRole === 'parent';
  const startEdit = (student) => {
    setEditingId(student.id);
    setForm({
      studentName: student.name || '',
      email: student.email || '',
      mobileNumber: student.mobileNumber || '',
      lrn: student.lrn || '',
      schoolName: student.schoolName || '',
      gradeLevel: student.gradeLevel || '',
      pickupAddress: student.pickupAddress || '',
      pickupLatitude: student.pickupLatitude || '10.6765',
      pickupLongitude: student.pickupLongitude || '122.9509',
      dropoffAddress: student.dropoffAddress || '',
      dropoffLatitude: student.dropoffLatitude || '10.6684',
      dropoffLongitude: student.dropoffLongitude || '123.0198',
      notes: student.notes || '',
      password: '',
    });
  };
  const updateField = (key, value) => setForm((current) => ({ ...current, [key]: value }));
  const updatePickup = ({ address, latitude, longitude }) => {
    setForm((current) => ({
      ...current,
      pickupAddress: address,
      pickupLatitude: latitude,
      pickupLongitude: longitude,
    }));
  };
  const updateDropoff = ({ address, latitude, longitude }) => {
    setForm((current) => ({
      ...current,
      dropoffAddress: address,
      dropoffLatitude: latitude,
      dropoffLongitude: longitude,
    }));
  };
  const saveStudent = async (studentId) => {
    await updateStudent(studentId, form);
    setEditingId(null);
  };
  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await refreshDashboard();
    } finally {
      setRefreshing(false);
    }
  };

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="students" />} refreshing={refreshing} onRefresh={handleRefresh}>
      <HeaderBlock
        eyebrow={isParent ? 'Child Accounts' : 'Assigned Children'}
        title={isParent ? 'Linked children under the parent account.' : 'Children assigned to this driver route.'}
        subtitle={isParent ? 'Each child should have a unique LRN and route information before transport planning.' : 'Drivers can review route details here, while profile ownership stays with the parents.'}
      />

      {students.map((student) => (
        <SectionCard key={student.id} title={student.name} subtitle={`${student.schoolName} - ${student.gradeLevel}`} icon="child">
          {editingId === student.id ? (
            <>
              <FormInput label="Child Name" value={form.studentName} onChangeText={(value) => updateField('studentName', value)} placeholder="Child name" />
              <FormInput label="Child Email" value={form.email} onChangeText={(value) => updateField('email', value)} placeholder="child@example.com" keyboardType="email-address" />
              <FormInput label="Child Mobile Number" value={form.mobileNumber} onChangeText={(value) => updateField('mobileNumber', value)} placeholder="09XXXXXXXXX" keyboardType="phone-pad" />
              <FormInput label="LRN" value={form.lrn} onChangeText={(value) => updateField('lrn', value)} placeholder="LRN" />
              <FormInput label="School Name" value={form.schoolName} onChangeText={(value) => updateField('schoolName', value)} placeholder="School" />
              <FormInput label="Grade Level" value={form.gradeLevel} onChangeText={(value) => updateField('gradeLevel', value)} placeholder="Grade level" />
              <AddressPinPicker label="Pickup Point" value={form.pickupAddress} latitude={form.pickupLatitude} longitude={form.pickupLongitude} onChange={updatePickup} />
              <AddressPinPicker label="Drop-off Point" value={form.dropoffAddress} latitude={form.dropoffLatitude} longitude={form.dropoffLongitude} onChange={updateDropoff} />
              <FormInput label="New Password" value={form.password} onChangeText={(value) => updateField('password', value)} placeholder="Leave blank to keep current password" secureTextEntry />
              <AppButton icon="save" label="Save Child" onPress={() => saveStudent(student.id)} />
              <AppButton icon="times" label="Cancel" variant="ghost" onPress={() => setEditingId(null)} />
            </>
          ) : (
            <>
              <Pill label={`LRN: ${student.lrn}`} />
              <InfoRow icon="envelope" label="Child login" value={student.email || '-'} />
              <InfoRow icon="map-marker-alt" label="Pickup" value={student.pickupAddress} />
              <InfoRow icon="flag-checkered" label="Drop-off" value={student.dropoffAddress} />
              <InfoRow icon="phone-alt" label="Emergency Contact" value={student.emergencyContact} />
              {isParent ? <AppButton icon="edit" label="Edit Child" variant="ghost" onPress={() => startEdit(student)} /> : null}
            </>
          )}
        </SectionCard>
      ))}

      {isParent ? <AppButton icon="user-plus" label="Add Child Account" onPress={() => navigation.navigate('AddStudent')} /> : null}
    </Screen>
  );
}
